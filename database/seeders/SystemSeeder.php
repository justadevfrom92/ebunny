<?php

namespace Database\Seeders;

use App\Models\IntegrationTest;
use App\Models\JobRun;
use App\Models\ReferenceRow;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** Sheriff: reference data tables and recent job history. */
class SystemSeeder extends Seeder
{
    public function run(): void
    {
        $tables = DatabaseSeeder::data('data_tables');
        foreach (config('admin.reference_tables') as $key => [$name]) {
            foreach ($tables[$name]['rows'] ?? [] as $i => $cells) {
                ReferenceRow::create(['table_key' => $key, 'position' => $i, 'cells' => array_map('strval', $cells)]);
            }
        }

        $byName = collect(config('admin.jobs'))->mapWithKeys(fn ($job, $command) => [$job[0] => $command]);
        foreach (DatabaseSeeder::data('crons') as $c) {
            $command = $byName[$c['name']] ?? null;
            if (! $command) {
                continue;
            }
            $start = Carbon::parse($c['last']);
            JobRun::create([
                'command' => $command, 'status' => $c['status'],
                'message' => $c['status'] === 'Failed' ? 'Sample failure from seed data' : null,
                'started_at' => $start, 'finished_at' => $start->copy()->addMilliseconds($c['ms']), 'duration_ms' => $c['ms'],
            ]);
        }

        // Test Connection results (Sheriff → APIs): a few per integration over the last month
        $admin = User::where('email', 'admin@example.com')->value('id');
        foreach (array_keys(config('admin.integrations')) as $n => $key) {
            foreach (range(1, 3) as $k) {
                $ok = ! ($key === 'innowatts' && $k === 1) && ! ($key === 'authnet' && $k <= 2);
                IntegrationTest::create(['integration' => $key, 'ok' => $ok, 'response_ms' => mt_rand(90, 900), 'user_id' => $admin,
                    'message' => $ok ? 'Connected (sample)' : ($key === 'authnet' ? 'Login ID not accepted (sample)' : 'Timed out after 10 seconds (sample)'),
                    'created_at' => now()->subDays(($k - 1) * 9 + $n % 5)->subHours($n)]);
            }
        }
    }
}
