<?php

namespace App\Http\Controllers\Backend\Astro;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessRateImport;
use App\Models\Market;
use App\Models\Plan;
use App\Models\Rate;
use App\Models\RateImport;
use App\Services\Catalog;
use App\Support\History;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Astro → Update Rates → Upload Rates: a CSV or Excel file is read in the background; when it's done the changes
 * wait for someone to confirm them (with an effective date) before they go live, or to discard them.
 */
class RateImportController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:4096', 'mimes:csv,txt,xlsx']], ['file.mimes' => 'Upload a CSV, tab-separated text or Excel (.xlsx) file.']);
        $file = $request->file('file');
        $import = RateImport::create(['user_id' => $request->user()->id, 'file_name' => $file->getClientOriginalName(),
            'path' => $file->storeAs('rate-imports', now()->format('YmdHis').'-'.preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName()), 'local')]);
        ProcessRateImport::dispatch($import->id);

        return redirect()->route('astro.rates.imports.show', $import)->with('status', 'Reading '.$import->file_name.' in the background. You\'ll get a notification when it\'s ready to confirm.');
    }

    public function show(RateImport $import): View
    {
        return view('backend.astro.rate-import', ['import' => $import->load(['user', 'applier'])]);
    }

    /** Make the changes live: new rate rows with the effective date. */
    public function confirm(Request $request, RateImport $import): RedirectResponse
    {
        abort_unless($import->status === 'ready', 422, 'Only a file that has finished reading can be confirmed.');
        $data = $request->validate(['effective_on' => ['required', 'date', 'after_or_equal:today']]);
        $changes = $import->changes ?? [];
        $plans = Plan::pluck('id')->flip();
        $markets = Market::pluck('id')->flip();
        DB::transaction(function () use ($changes, $plans, $markets, $data, $request, $import) {
            foreach ($changes as $c) {
                if (isset($plans[$c['plan_id']], $markets[$c['market_id']])) {
                    Rate::create(['plan_id' => $c['plan_id'], 'market_id' => $c['market_id'], 'energy' => $c['new'], 'effective_on' => $data['effective_on'], 'created_by' => $request->user()->id]);
                }
            }
            $import->update(['status' => 'applied', 'effective_on' => $data['effective_on'], 'applied_by' => $request->user()->id, 'applied_at' => now()]);
        });
        History::record(['model' => 'Rate_model', 'group' => 'Admin Changes', 'action' => 'updated', 'record_id' => $import->id,
            'summary' => count($changes).' rates from '.$import->file_name.' confirmed, effective '.$data['effective_on']]);

        return redirect()->route('astro.rates.imports.show', $import)->with('status', count($changes).' rates are live, effective '.Carbon::parse($data['effective_on'])->format('n/j/Y').'.');
    }

    public function discard(RateImport $import): RedirectResponse
    {
        abort_if($import->status === 'applied', 422, 'These rates are already live.');
        $import->update(['status' => 'discarded']);

        return redirect()->route('astro.rates.edit')->with('status', $import->file_name.' discarded. No rates changed.');
    }

    /** A file to start from: every active plan and market with its current rate, in the layout the upload reads. */
    public function template(Catalog $catalog): StreamedResponse
    {
        $rates = $catalog->latestRates();
        $markets = Market::orderBy('name')->get();

        return response()->streamDownload(function () use ($rates, $markets) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Plan', 'Plan Name', ...$markets->pluck('short')->all()]);
            foreach (Plan::where('active', true)->orderBy('type')->orderBy('term')->orderBy('name')->get() as $p) {
                fputcsv($out, [$p->internal, $p->name, ...$markets->map(fn ($m) => isset($rates[$p->id.'-'.$m->id]) ? number_format($rates[$p->id.'-'.$m->id]->energy, 3, '.', '') : '')->all()]);
            }
            fclose($out);
        }, 'rates-'.today()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
