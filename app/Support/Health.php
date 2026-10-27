<?php

namespace App\Support;

use App\Http\Controllers\Backend\Lando\DashboardController;
use App\Models\AdminNotification;
use App\Models\Campaign;
use App\Models\Heartbeat;
use App\Models\JobRun;
use App\Models\ReportRun;
use App\Models\SupportTicket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The heartbeat health system. Everything that runs on its own (the scheduler, the queue worker, background
 * reports, crons, campaigns) is checked every minute by `php artisan et:heartbeat`, along with the server.
 * Each check is ok, warn or bad; the overall status is the worst of them. Sheriff users are notified when
 * a check goes bad, and again when everything is back to ok.
 */
class Health
{
    /** key => [name, group, what it checks, where to look (route)] */
    public const CHECKS = [
        'scheduler' => ['Scheduler', 'Background', 'The cron that runs php artisan schedule:run every minute', 'sheriff.jobs.index'],
        'queue_worker' => ['Queue worker', 'Background', 'php artisan queue:work is picking up jobs', 'sheriff.jobs.index'],
        'queue_backlog' => ['Queue backlog', 'Background', 'Jobs waiting to run, and how long the oldest has waited', 'sheriff.jobs.index'],
        'failed_jobs' => ['Failed jobs', 'Background', 'Queued jobs that failed in the last 24 hours', 'sheriff.jobs.index'],
        'crons' => ['Crons', 'Jobs & Actions', 'Scheduled tasks that failed or are stuck running', 'sheriff.jobs.index'],
        'reports' => ['Background reports', 'Jobs & Actions', 'Walker and Corral reports stuck or failing', null],
        'campaigns' => ['Campaigns', 'Jobs & Actions', 'Scheduled campaigns that should have sent', null],
        'tickets' => ['Support tickets', 'Jobs & Actions', 'Tickets nobody has picked up for 4 hours', null],
        'database' => ['Database', 'Server', 'How fast the database answers', null],
        'cache' => ['Cache', 'Server', 'Saving and reading back from the cache', null],
        'storage' => ['Storage & disk', 'Server', 'storage/ is writable and the disk has room', null],
        'errors' => ['Errors logged', 'Server', 'Errors written to storage/logs in the last 24 hours', null],
        'website' => ['Website settings', 'Server', 'Debug mode, HTTPS, email and payments', null],
    ];

    /** Mark something that runs on its own as alive. */
    public static function pulse(string $key, array $meta = []): void
    {
        DB::table('heartbeat_pulses')->updateOrInsert(['key' => $key], ['beat_at' => now(), 'meta' => json_encode($meta)]);
    }

    public static function lastPulse(string $key): ?Carbon
    {
        $at = DB::table('heartbeat_pulses')->where('key', $key)->value('beat_at');

        return $at ? Carbon::parse($at) : null;
    }

    /**
     * Every check now: key => [state, detail, fix].
     *
     * @return array<string, array{0: string, 1: string, 2: ?string}>
     */
    public static function checks(): array
    {
        $r = [];
        $ago = fn ($t) => $t ? $t->diffForHumans() : 'never';

        $s = self::lastPulse('scheduler');
        $min = $s ? $s->diffInMinutes(now(), true) : null;
        $r['scheduler'] = $min === null ? ['bad', 'No beat yet', 'Add the cron entry: * * * * * cd /path && php artisan schedule:run (php artisan start runs it for you).']
            : ($min < 3 ? ['ok', 'Last beat '.$ago($s), null] : [$min < 15 ? 'warn' : 'bad', 'Last beat '.$ago($s), 'The scheduler has stopped: check the cron entry on the server.']);

        $q = self::lastPulse('queue');
        $min = $q ? $q->diffInMinutes(now(), true) : null;
        $r['queue_worker'] = $min === null ? ['bad', 'No ping answered yet', 'Start a worker: php artisan queue:work (php artisan start runs one).']
            : ($min < 3 ? ['ok', 'Answered a ping '.$ago($q), null] : [$min < 15 ? 'warn' : 'bad', 'Last answered a ping '.$ago($q), 'Restart the queue worker (Supervisor or systemd on the server).']);

        $waiting = DB::table('jobs')->count();
        $oldest = DB::table('jobs')->min('available_at');
        $age = $oldest ? (int) floor((time() - (int) $oldest) / 60) : 0;
        $r['queue_backlog'] = [$waiting === 0 || $age < 5 ? 'ok' : ($age < 30 ? 'warn' : 'bad'), $waiting ? $waiting.' waiting · oldest '.$age.' min' : 'Nothing waiting', $age >= 5 ? 'Jobs are piling up: is the worker running and keeping up?' : null];

        $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
        $r['failed_jobs'] = [$failed === 0 ? 'ok' : ($failed <= 5 ? 'warn' : 'bad'), $failed ? $failed.' failed in 24 hours' : 'None in 24 hours', $failed ? 'Look at the error on each, fix it, then retry them: php artisan queue:retry all.' : null];

        $cronFailed = JobRun::where('status', 'Failed')->where('started_at', '>=', now()->subDay())->count();
        $stuck = JobRun::where('status', 'Running')->where('started_at', '<', now()->subHour())->count();
        $r['crons'] = [$stuck ? 'bad' : ($cronFailed ? 'warn' : 'ok'), collect([$cronFailed ? $cronFailed.' failed in 24 hours' : null, $stuck ? $stuck.' stuck running over an hour' : null])->filter()->implode(' · ') ?: 'All ran fine in 24 hours',
            $stuck || $cronFailed ? 'Sheriff → Crons shows each run and its message.' : null];

        $stuckReports = ReportRun::whereIn('status', ['queued', 'running'])->where('created_at', '<', now()->subHour())->count();
        $failedReports = ReportRun::where('status', 'failed')->where('finished_at', '>=', now()->subDay())->count();
        $r['reports'] = [$stuckReports ? 'bad' : ($failedReports ? 'warn' : 'ok'), collect([$stuckReports ? $stuckReports.' waiting over an hour' : null, $failedReports ? $failedReports.' failed in 24 hours' : null])->filter()->implode(' · ') ?: 'All finished',
            $stuckReports ? 'Reports need the queue worker running.' : ($failedReports ? 'Walker → Report Status shows the errors.' : null)];

        $late = Campaign::where('status', 'scheduled')->where('scheduled_at', '<', now()->subMinutes(15))->count();
        $r['campaigns'] = [$late ? 'bad' : 'ok', $late ? $late.' scheduled but not sent' : 'None late', $late ? 'The et:campaigns cron sends them; is the scheduler running?' : null];

        $waitingTickets = SupportTicket::active()->whereNull('assigned_to')->where('created_at', '<', now()->subHours(4))->count();
        $r['tickets'] = [$waitingTickets ? 'warn' : 'ok', $waitingTickets ? $waitingTickets.' waiting over 4 hours with nobody on them' : 'None waiting over 4 hours', $waitingTickets ? 'Ranger → Tickets → Unassigned.' : null];

        // The server checks Lando already runs for the website
        try {
            $site = collect(DashboardController::checks());
        } catch (Throwable $e) {
            $site = collect();
        }
        $pick = function (array $names) use ($site) {
            $c = $site->whereIn('name', $names);

            return $c->isEmpty() ? ['warn', 'Couldn\'t check', null]
                : [$c->contains('state', 'bad') ? 'bad' : ($c->contains('state', 'warn') ? 'warn' : 'ok'), $c->pluck('found')->implode(' · '), $c->pluck('fix')->filter()->first()];
        };
        $r['database'] = $pick(['Database']);
        $r['cache'] = $pick(['Cache']);
        $r['storage'] = $pick(['Storage', 'Disk space']);
        $r['errors'] = $pick(['Errors in the last 24 hours']);
        $settings = $site->where('group', 'Settings');
        $bad = $settings->where('state', '!=', 'ok');
        $r['website'] = [$settings->contains('state', 'bad') ? 'bad' : ($bad->isNotEmpty() ? 'warn' : 'ok'), $bad->isEmpty() ? 'All set' : $bad->pluck('name')->implode(', ').' need a look', $bad->pluck('fix')->filter()->first()];

        return $r;
    }

    public static function overall(array $results): string
    {
        $states = array_column($results, 0);

        return in_array('bad', $states, true) ? 'bad' : (in_array('warn', $states, true) ? 'warn' : 'ok');
    }

    /** Run every check, keep the result, and tell Sheriff users when something goes bad or comes back. */
    public static function run(): Heartbeat
    {
        $t = microtime(true);
        $results = self::checks();
        $prev = Heartbeat::latest('checked_at')->latest('id')->first();
        $beat = Heartbeat::create(['checked_at' => now(), 'status' => self::overall($results), 'results' => array_map(fn ($r) => [$r[0], $r[1]], $results),
            'duration_ms' => (int) round((microtime(true) - $t) * 1000)]);

        $newlyBad = collect($results)->filter(fn ($r, $k) => $r[0] === 'bad' && ($prev?->results[$k][0] ?? 'ok') !== 'bad')->keys();
        if ($newlyBad->isNotEmpty()) {
            AdminNotification::notify(AdminNotification::appUsers('sheriff'), 'health', 'Heartbeat: '.$newlyBad->map(fn ($k) => self::CHECKS[$k][0])->implode(', ').' needs attention',
                $newlyBad->map(fn ($k) => self::CHECKS[$k][0].': '.$results[$k][1])->implode(' · '), route('sheriff.heartbeat'));
        } elseif ($prev && $prev->status === 'bad' && $beat->status !== 'bad') {
            AdminNotification::notify(AdminNotification::appUsers('sheriff'), 'health', 'Heartbeat: back to '.($beat->status === 'ok' ? 'healthy' : 'warnings only'), null, route('sheriff.heartbeat'));
        }
        Heartbeat::where('checked_at', '<', now()->subDays(14))->delete();   // keep two weeks

        return $beat;
    }
}
