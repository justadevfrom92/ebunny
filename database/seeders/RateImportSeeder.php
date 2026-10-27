<?php

namespace Database\Seeders;

use App\Models\RateImport;
use App\Models\User;
use App\Services\Catalog;
use Illuminate\Database\Seeder;

/** Sample rate uploads (fictional files): one confirmed and live, one waiting to be confirmed, one that failed. */
class RateImportSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->value('id');
        $pricing = User::where('email', 'pricing@example.com')->value('id') ?? $admin;
        $latest = app(Catalog::class)->latestRates()->take(14)->values();
        $changes = fn (float $by, int $n) => $latest->take($n)->map(fn ($r) => ['plan_id' => $r->plan_id, 'market_id' => $r->market_id, 'plan' => $r->plan?->name ?? 'Plan '.$r->plan_id,
            'internal' => $r->plan?->internal ?? '', 'market' => $r->market?->short ?? '', 'old' => round((float) $r->energy, 3), 'new' => round((float) $r->energy + $by, 3)])->values()->all();

        RateImport::create(['user_id' => $pricing, 'file_name' => 'rates-2026-09-fall.csv', 'path' => 'rate-imports/sample-fall.csv', 'status' => 'applied', 'rows' => 70, 'unchanged' => 58,
            'changes' => $changes(-0.2, 12), 'problems' => [], 'effective_on' => now()->subDays(20)->toDateString(), 'applied_by' => $admin, 'applied_at' => now()->subDays(21),
            'finished_at' => now()->subDays(21), 'created_at' => now()->subDays(21), 'updated_at' => now()->subDays(21)]);
        RateImport::create(['user_id' => $pricing, 'file_name' => 'November rates.xlsx', 'path' => 'rate-imports/sample-november.xlsx', 'status' => 'ready', 'rows' => 70, 'unchanged' => 54,
            'changes' => $changes(0.3, 14), 'problems' => [['line' => 18, 'message' => 'No plan "BYOP-OLD"'], ['line' => 41, 'message' => '"n/a" is not a number']],
            'finished_at' => now()->subHours(2), 'created_at' => now()->subHours(2), 'updated_at' => now()->subHours(2)]);
        RateImport::create(['user_id' => $admin, 'file_name' => 'rates-export (1).pdf.csv', 'path' => 'rate-imports/sample-bad.csv', 'status' => 'failed',
            'error' => 'Couldn\'t find the columns. Use Plan, Market and Rate columns, or Plan then one column per market (ONCOR, CNP, AEPN, AEPC, TNMP).',
            'finished_at' => now()->subDays(3), 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)]);
    }
}
