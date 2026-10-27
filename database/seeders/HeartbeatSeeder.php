<?php

namespace Database\Seeders;

use App\Models\Heartbeat;
use App\Models\HeartbeatAction;
use App\Models\User;
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
            if ($m >= 840 && $m < 880) {
                $r['campaigns'] = ['down', 'Taken down by Admin Demo: holding the fall promo until pricing is approved'];
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
        // Controls people used (fictional): the queue worker restarted during its outage, campaigns held and released, failed jobs retried
        $admin = User::where('email', 'admin@example.com')->value('id');
        foreach ([['campaigns', 'down', 880, 'Campaigns taken down. Scheduled campaigns are held instead of sent.'], ['campaigns', 'up', 840, 'Campaigns is back up.'],
            ['campaigns', 'send', 838, 'OK: 1 campaign sent, 412 messages.'], ['queue_worker', 'restart', 184, 'Broadcasting queue restart signal.'],
            ['queue_worker', 'ping', 183, 'Ping queued. The worker answers when it picks it up.'], ['failed_jobs', 'retry', 64, '2 failed jobs put back in the queue.'],
            ['scheduler', 'run', 498, 'Running [et:heartbeat] ... DONE'], ['cache', 'clear', 1300, 'Application cache cleared successfully.'],
            ['database', 'optimize', 1250, 'PRAGMA optimize ran.'], ['errors', 'archive', 1200, 'Archived to storage/logs/laravel-sample.log.'],
            ['website', 'check', 30, 'Healthy: All set'], ['tickets', 'notify', 20, '3 managers notified about 2 tickets.']] as [$key, $action, $mins, $out]) {
            HeartbeatAction::create(['check_key' => $key, 'action' => $action, 'user_id' => $admin, 'ok' => true, 'output' => $out, 'duration_ms' => mt_rand(4, 900), 'created_at' => now()->subMinutes($mins)]);
        }

        Health::pulse('scheduler');
        Health::pulse('queue', ['waited_s' => 2]);
        Health::run();
    }
}
