<?php

namespace App\Http\Controllers\Backend\Lando;

use App\Http\Controllers\Controller;
use App\Models\Market;
use App\Models\Plan;
use App\Models\Rate;
use App\Services\Catalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Rates are kept as history. Saving adds new rows with an effective date; the
 * website uses the newest rate effective today or earlier.
 */
class RateController extends Controller
{
    public function index(Request $request, Catalog $catalog): View
    {
        $f = $request->validate([
            'plan' => ['nullable', 'string', 'max:100'],
            'market' => ['nullable', 'exists:markets,id'],
            'kwh' => ['nullable', Rule::in([500, 1000, 2000])],
        ]);
        $kwh = (int) ($f['kwh'] ?? 1000);
        $plans = Plan::where('active', true)
            ->when($f['plan'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('internal', 'like', "%$s%")))
            ->orderBy('type')->orderBy('term')->orderBy('name')->get();
        $markets = Market::orderBy('name')->get();
        $shown = isset($f['market']) ? $markets->where('id', (int) $f['market']) : $markets;
        $rates = $catalog->currentRates();
        $fees = $catalog->currentFees();

        $rows = [];
        foreach ($plans as $p) {
            foreach ($shown as $m) {
                $r = $rates[$p->id.'-'.$m->id] ?? null;
                $fee = $fees[$m->id] ?? null;
                if (! $r || ! $fee) {
                    continue;
                }
                $rows[] = ['plan' => $p, 'market' => $m, 'rate' => $r, 'fee' => $fee,
                    'avg' => Catalog::averagePrice($r->energy, $fee->per_kwh, $fee->per_bill, $p->mrc, $kwh)];
            }
        }

        return view('backend.lando.rates.index', ['f' => $f, 'kwh' => $kwh, 'markets' => $markets, 'rows' => $rows]);
    }

    /** ?plan=ID shows one plan's rates (the "rates" link on View Plans); ?all=1 includes inactive plans. */
    public function edit(Request $request, Catalog $catalog): View
    {
        $plan = $request->integer('plan') ?: null;

        return view('backend.lando.rates.edit', [
            'plans' => Plan::when(! $request->boolean('all') && ! $plan, fn ($q) => $q->where('active', true))
                ->when($plan, fn ($q, $id) => $q->whereKey($id))->orderBy('type')->orderBy('term')->orderBy('name')->get(),
            'onePlan' => $plan ? Plan::find($plan) : null,
            'showAll' => $request->boolean('all'),
            'markets' => Market::orderBy('name')->get(),
            'rates' => $catalog->latestRates(),
            'pending' => Rate::whereDate('effective_on', '>', today())->count(),
        ]);
    }

    public function update(Request $request, Catalog $catalog): RedirectResponse
    {
        $data = $request->validate([
            'effective_on' => ['required', 'date'],
            'rates' => ['required', 'array'],
            'rates.*' => ['array'],
            'rates.*.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $latest = $catalog->latestRates();
        $planIds = Plan::pluck('id')->flip();
        $marketIds = Market::pluck('id')->flip();
        $changed = 0;

        DB::transaction(function () use ($data, $latest, $planIds, $marketIds, $request, &$changed) {
            foreach ($data['rates'] as $planId => $byMarket) {
                foreach ($byMarket as $marketId => $energy) {
                    if ($energy === null || $energy === '' || ! isset($planIds[$planId], $marketIds[$marketId])) {
                        continue;
                    }
                    $current = $latest[$planId.'-'.$marketId] ?? null;
                    if ($current && abs($current->energy - (float) $energy) < 0.0005) {
                        continue;
                    }
                    Rate::create(['plan_id' => $planId, 'market_id' => $marketId, 'energy' => round((float) $energy, 3),
                        'effective_on' => $data['effective_on'], 'created_by' => $request->user()->id]);
                    $changed++;
                }
            }
        });

        return redirect()->route('astro.rates.edit', array_filter(['plan' => $request->integer('plan') ?: null]))->with('status', $changed ? $changed.' rates saved, effective '.$data['effective_on'] : 'No changes to save');
    }
}
