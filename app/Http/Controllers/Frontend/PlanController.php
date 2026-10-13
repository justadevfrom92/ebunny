<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Market;
use App\Models\Plan;
use App\Models\ZipRange;
use App\Services\Catalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A page for each plan (/plans/{slug}): the average price at 500, 1,000 and 2,000 kWh in every
 * utility area the plan is sold in, plan facts (term, rate type, base charge, ETF, renewable
 * share), what's included, the plan documents and Sign Up. ?zip= picks out the visitor's area.
 */
class PlanController extends Controller
{
    public function show(Request $request, Plan $plan, Catalog $catalog): View
    {
        abort_unless($plan->active, 404);
        $rates = $catalog->currentRates();
        $fees = $catalog->currentFees();
        $markets = Market::where('status', 'Active')->orderBy('short')->get()
            ->filter(fn ($m) => isset($rates[$plan->id.'-'.$m->id], $fees[$m->id]))->values();
        $prices = $markets->mapWithKeys(fn ($m) => [$m->id => collect([500, 1000, 2000])->mapWithKeys(fn ($k) => [$k => Catalog::averagePrice(
            $rates[$plan->id.'-'.$m->id]->energy, $fees[$m->id]->per_kwh, $fees[$m->id]->per_bill, $plan->mrc, $k)])]);

        $zip = preg_match('/^\d{5}$/', (string) $request->query('zip')) ? $request->query('zip') : null;
        $here = $zip ? ZipRange::where('zip_from', '<=', $zip)->where('zip_to', '>=', $zip)->value('market_id') : null;
        $here = $here && $prices->has($here) ? $here : null;
        $main = $here ?? $markets->first()?->id;

        return view('frontend.plans.show', [
            'plan' => $plan, 'markets' => $markets, 'prices' => $prices, 'rates' => $rates, 'fees' => $fees, 'zip' => $zip, 'here' => $here, 'main' => $main,
            'others' => Plan::where('active', true)->where('type', $plan->type)->where('id', '!=', $plan->id)
                ->orderByRaw('abs(term - ?)', [$plan->term])->limit(3)->get(),
        ]);
    }
}
