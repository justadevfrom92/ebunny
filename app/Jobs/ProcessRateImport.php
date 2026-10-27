<?php

namespace App\Jobs;

use App\Models\AdminNotification;
use App\Models\Market;
use App\Models\Plan;
use App\Models\RateImport;
use App\Services\Catalog;
use App\Support\SheetReader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads an uploaded rate file and works out what would change. Nothing goes live here: the changes wait on the
 * import until someone confirms them.
 *
 * Two layouts are read:
 *  - one row per rate: columns Plan, Market, Rate (¢/kWh), in any order, with a header row;
 *  - a grid like Update Rates: Plan in the first column, then one column per market (name or short name).
 * Plans match on internal code, name or ID; markets on name or short name.
 */
class ProcessRateImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $importId) {}

    public function handle(Catalog $catalog): void
    {
        try {
            $this->read($catalog);
        } catch (Throwable $e) {
            $this->failed($e);   // the import shows the reason; nothing else to retry
        }
    }

    private function read(Catalog $catalog): void
    {
        $import = RateImport::findOrFail($this->importId);
        $import->update(['status' => 'processing']);
        $rows = SheetReader::read(Storage::disk('local')->path($import->path), $import->file_name);
        if (count($rows) < 2) {
            throw new \RuntimeException('The file has no rates in it (it needs a header row and at least one rate).');
        }

        $plans = Plan::all();
        $markets = Market::all();
        $findPlan = fn (string $v) => $plans->first(fn ($p) => strcasecmp($p->internal, $v) === 0 || strcasecmp($p->name, $v) === 0 || (string) $p->id === $v);
        $findMarket = fn (string $v) => $markets->first(fn ($m) => strcasecmp($m->name, $v) === 0 || strcasecmp($m->short, $v) === 0);
        $header = array_map(fn ($h) => Str::lower(trim($h)), array_shift($rows));
        $col = fn (array $names) => collect($header)->search(fn ($h) => in_array($h, $names, true) || collect($names)->contains(fn ($n) => str_starts_with($h, $n)));
        $planCol = $col(['plan', 'internal', 'plan code', 'plan name']);
        $marketCol = $col(['market', 'tdsp', 'utility']);
        $rateCol = $col(['rate', 'energy', 'price', 'cents']);

        // Read every rate in the file as [line, plan text, market text, value]
        $cells = [];
        if ($marketCol !== false && $rateCol !== false) {
            foreach ($rows as $i => $r) {
                $cells[] = [$i + 2, $r[$planCol === false ? 0 : $planCol] ?? '', $r[$marketCol] ?? '', $r[$rateCol] ?? ''];
            }
        } else {
            $marketCols = [];
            foreach ($header as $c => $h) {
                if ($c !== ($planCol === false ? 0 : $planCol) && ($m = $findMarket($h))) {
                    $marketCols[$c] = $m->short;
                }
            }
            if (! $marketCols) {
                throw new \RuntimeException('Couldn\'t find the columns. Use Plan, Market and Rate columns, or Plan then one column per market ('.$markets->pluck('short')->implode(', ').').');
            }
            foreach ($rows as $i => $r) {
                foreach ($marketCols as $c => $short) {
                    if (trim($r[$c] ?? '') !== '') {
                        $cells[] = [$i + 2, $r[$planCol === false ? 0 : $planCol] ?? '', $short, $r[$c]];
                    }
                }
            }
        }

        $latest = $catalog->latestRates();
        $changes = [];
        $problems = [];
        $unchanged = 0;
        $seen = [];
        foreach ($cells as [$line, $planText, $marketText, $value]) {
            $value = trim(str_replace(['¢', '$', ' '], '', (string) $value));
            $plan = $findPlan(trim($planText));
            $market = $findMarket(trim($marketText));
            $problem = match (true) {
                ! $plan => 'No plan "'.$planText.'"',
                ! $market => 'No market "'.$marketText.'"',
                ! is_numeric($value) => '"'.$value.'" is not a number',
                (float) $value <= 0 || (float) $value > 100 => $value.'¢ is outside 0–100 ¢/kWh',
                isset($seen[$plan->id.'-'.$market->id]) => $plan->internal.' / '.$market->short.' is in the file twice (the first one is used)',
                default => null,
            };
            if ($problem) {
                $problems[] = ['line' => $line, 'message' => $problem];

                continue;
            }
            $seen[$plan->id.'-'.$market->id] = true;
            $old = $latest[$plan->id.'-'.$market->id]->energy ?? null;
            if ($old !== null && abs((float) $old - (float) $value) < 0.0005) {
                $unchanged++;

                continue;
            }
            $changes[] = ['plan_id' => $plan->id, 'market_id' => $market->id, 'plan' => $plan->name, 'internal' => $plan->internal, 'market' => $market->short,
                'old' => $old !== null ? round((float) $old, 3) : null, 'new' => round((float) $value, 3)];
        }

        $import->update(['status' => 'ready', 'rows' => count($cells), 'unchanged' => $unchanged, 'changes' => $changes, 'problems' => $problems, 'finished_at' => now()]);
        AdminNotification::notify($import->user, 'file', 'Rate file ready to confirm: '.$import->file_name,
            count($changes).' '.Str::plural('rate', count($changes)).' would change'.($problems ? ', '.count($problems).' '.Str::plural('problem', count($problems)) : '').'. Confirm to make them live.',
            route('astro.rates.imports.show', $import));
    }

    public function failed(Throwable $e): void
    {
        RateImport::whereKey($this->importId)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000), 'finished_at' => now()]);
        if ($import = RateImport::find($this->importId)) {
            AdminNotification::notify($import->user, 'file', 'Rate file failed: '.$import->file_name, mb_substr($e->getMessage(), 0, 200), route('astro.rates.imports.show', $import));
        }
    }
}
