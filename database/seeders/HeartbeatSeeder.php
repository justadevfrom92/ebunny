<?php

namespace Database\Seeders;

use App\Models\Heartbeat;
use App\Support\Health;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sample heartbeat history (fictional): a check every minute for the last 24 hours, mostly healthy, with a
 * queue backlog one afternoon, the queue worker down for a few minutes, and a 10-minute gap when the
 * scheduler stopped. Two failed jobs. Then a real check now.
 */
class HeartbeatSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(31);
        $ok = array_map(fn () => ['ok', 'Fine'], Health::CHECKS);
        $rows = [];
        for ($m = 24 * 60; $m >= 1; $m--) {
            if ($m >= 500 && $m < 510) {
                continue;   // the scheduler stopped: no checks ran
            }
            $r = $ok;
            if ($m >= 300 && $m < 325) {
                $r['queue_backlog'] = ['warn', '64 waiting · oldest 12 min'];
            }
            if ($m >= 180 && $m < 186) {
                $r['queue_worker'] = ['bad', 'Last answered a ping 6 minutes ago'];
            }
            if ($m >= 60 && $m < 75) {
                $r['failed_jobs'] = ['warn', '2 failed in 24 hours'];
            }
            $rows[] = ['checked_at' => now()->subMinutes($m)->startOfMinute(), 'status' => Health::overall($r), 'results' => json_encode($r), 'duration_ms' => mt_rand(18, 90)];
        }
        foreach (array_chunk($rows, 300) as $chunk) {
            Heartbeat::insert($chunk);
        }

        foreach ([['App\\Jobs\\RunReport', 'Illuminate\\Database\\QueryException: SQLSTATE[HY000]: General error: 5 database is locked (sample)', 70],
            ['App\\Jobs\\HeartbeatPing', 'Illuminate\\Queue\\TimeoutExceededException: The job has timed out (sample)', 185]] as [$job, $error, $mins]) {
            DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
                'payload' => json_encode(['displayName' => $job, 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call']), 'exception' => $error."\n#0 {main}", 'failed_at' => now()->subMinutes($mins)]);
        }
        Health::pulse('scheduler');
        Health::pulse('queue', ['waited_s' => 2]);
        Health::run();
    }
}
