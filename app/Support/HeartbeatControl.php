<?php

namespace App\Support;

use App\Jobs\HeartbeatPing;
use App\Jobs\RunReport;
use App\Models\AdminNotification;
use App\Models\HeartbeatAction;
use App\Models\JobRun;
use App\Models\RangerAgent;
use App\Models\ReportRun;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * The controls on each heartbeat check: restart, reboot, take down and bring back up, clear, retry and so on.
 * Taking something down pauses it on purpose (the scheduler's jobs, the queue, crons, reports, campaign sends
 * or the public website); the heartbeat shows it as "Taken Down" instead of a problem until it's brought up.
 */
class HeartbeatControl
{
    public const MAINTENANCE = 'We\'re doing some maintenance. Please check back in a few minutes.';

    /** Checks that can be taken down, and what that means. */
    public const DOWNABLE = [
        'scheduler' => 'Scheduled jobs stop running (the heartbeat itself keeps checking).',
        'queue_worker' => 'The queue is paused: workers stay up but pick up no jobs.',
        'crons' => 'Every cron is skipped until it\'s brought back up.',
        'reports' => 'Background reports wait in the queue instead of running.',
        'campaigns' => 'Scheduled campaigns are held instead of sent.',
        'website' => 'The public website shows a maintenance page. The admin and My Account keep working.',
    ];

    /** action => [label, what it does, tone (danger asks harder)] for one check. */
    public static function actions(string $key): array
    {
        $a = ['check' => ['Run Check Now', 'Run this check again and save the result.', '']];
        $a += match ($key) {
            'scheduler' => ['run' => ['Run Scheduler Now', 'Run php artisan schedule:run once: anything due runs now.', ''],
                'restart' => ['Restart', 'Bring the scheduler back up if it was taken down, then run it now.', '']],
            'queue_worker' => ['ping' => ['Send a Ping', 'Queue a ping job; the worker answers when it picks it up.', ''],
                'restart' => ['Restart Workers', 'php artisan queue:restart: each worker finishes its job and restarts with fresh code.', ''],
                'reboot' => ['Reboot Queue', 'Resume the queue if paused, restart the workers and send a ping.', '']],
            'queue_backlog' => ['work' => ['Run Next Job', 'Run the next waiting job now (php artisan queue:work --once).', ''],
                'clear' => ['Clear Waiting Jobs', 'Delete every job waiting in the queue. They won\'t run.', 'danger']],
            'failed_jobs' => ['retry' => ['Retry All', 'Put every failed job back in the queue (php artisan queue:retry all).', ''],
                'flush' => ['Delete All', 'Delete every failed job (php artisan queue:flush).', 'danger']],
            'crons' => ['reset' => ['Reset Stuck Runs', 'Mark runs stuck over an hour as failed so they can run again.', '']],
            'reports' => ['restart' => ['Restart Stuck Reports', 'Queue reports waiting over an hour again.', ''],
                'cancel' => ['Cancel Stuck Reports', 'Mark reports waiting over an hour as failed.', 'danger']],
            'campaigns' => ['send' => ['Send Due Now', 'Run php artisan et:campaigns now: due campaigns are sent.', '']],
            'tickets' => ['notify' => ['Notify Managers', 'Send Ranger managers a notification about tickets nobody has picked up.', '']],
            'database' => ['reconnect' => ['Reconnect', 'Drop the database connection and open a new one.', ''],
                'optimize' => ['Optimize', 'Let the database refresh its statistics (SQLite: PRAGMA optimize).', '']],
            'cache' => ['clear' => ['Clear Cache', 'php artisan cache:clear: everything cached is built again when next used.', ''],
                'reboot' => ['Reboot App Caches', 'php artisan optimize:clear: config, routes, views, events and the cache.', 'danger']],
            'storage' => ['views' => ['Clear Compiled Views', 'php artisan view:clear: frees space under storage/framework/views.', ''],
                'link' => ['Link Storage', 'php artisan storage:link: makes public/storage point at storage/app/public.', '']],
            'errors' => ['archive' => ['Archive Log', 'Rename storage/logs/laravel.log with today\'s date and start a new one.', '']],
            'website' => ['reboot' => ['Reboot', 'php artisan optimize:clear, then check the website settings again.', 'danger']],
            default => [],
        };
        if (isset(self::DOWNABLE[$key])) {
            $a['down'] = ['Take Down', self::DOWNABLE[$key], 'danger'];
            $a['up'] = ['Bring Up', 'Undo the take down: it runs again.', ''];
        }

        return $a;
    }

    /** @return array<string, array{state: string, reason: ?string, user_id: ?int, changed_at: ?string}> */
    public static function states(): array
    {
        return Cache::rememberForever('heartbeat_controls', fn () => DB::table('heartbeat_controls')->get()->keyBy('key')->map(fn ($r) => (array) $r)->all());
    }

    public static function isDown(string $key): bool
    {
        try {
            return (self::states()[$key]['state'] ?? 'up') === 'down';
        } catch (Throwable) {
            return false;   // before the table exists (fresh install, migrations)
        }
    }

    /** Run one control. @return array{0: bool, 1: string} */
    public static function perform(string $key, string $action, ?User $user, ?string $reason = null): array
    {
        abort_unless(isset(Health::CHECKS[$key], self::actions($key)[$action]), 404);
        $t = microtime(true);
        try {
            [$ok, $out] = self::run($key, $action, $user, $reason);
        } catch (Throwable $e) {
            [$ok, $out] = [false, $e->getMessage()];
        }
        HeartbeatAction::create(['check_key' => $key, 'action' => $action, 'user_id' => $user?->id, 'ok' => $ok, 'output' => mb_substr($out, 0, 4000),
            'duration_ms' => (int) round((microtime(true) - $t) * 1000), 'created_at' => now()]);
        History::record(['model' => 'Heartbeat_model', 'group' => 'Admin Changes', 'record_id' => null, 'action' => $action,
            'summary' => Health::CHECKS[$key][0].': '.self::actions($key)[$action][0].($ok ? '' : ' (failed)')]);
        if ($action !== 'check') {
            Health::run();   // so the page shows the result of what was just done
        }

        return [$ok, $out];
    }

    private static function artisan(string $command, array $args = []): array
    {
        $code = Artisan::call($command, $args);

        return [$code === 0, trim(Artisan::output()) ?: 'Done.'];
    }

    private static function setState(string $key, string $state, ?User $user, ?string $reason): void
    {
        DB::table('heartbeat_controls')->updateOrInsert(['key' => $key], ['state' => $state, 'reason' => $state === 'down' ? $reason : null, 'user_id' => $user?->id, 'changed_at' => now()]);
        Cache::forget('heartbeat_controls');
    }

    private static function run(string $key, string $action, ?User $user, ?string $reason): array
    {
        if ($action === 'check') {
            $r = Health::checks()[$key];

            return [true, ucfirst($r[0] === 'ok' ? 'healthy' : $r[0]).': '.$r[1]];
        }
        if ($action === 'down' || $action === 'up') {
            if ($key === 'queue_worker') {
                self::artisan($action === 'down' ? 'queue:pause' : 'queue:continue', ['queue' => config('queue.default').':default']);
            }
            self::setState($key, $action, $user, $reason);

            return [true, $action === 'down' ? Health::CHECKS[$key][0].' taken down. '.self::DOWNABLE[$key] : Health::CHECKS[$key][0].' is back up.'];
        }

        return match ($key.'.'.$action) {
            'scheduler.run' => self::artisan('schedule:run'),
            'scheduler.restart' => (function () use ($user) {
                self::setState('scheduler', 'up', $user, null);
                Health::pulse('scheduler');
                [$ok, $out] = self::artisan('schedule:run');

                return [$ok, "Scheduler is up.\n".$out];
            })(),
            'queue_worker.ping' => (function () {
                HeartbeatPing::dispatch(now()->toDateTimeString());

                return [true, 'Ping queued. The worker answers when it picks it up.'];
            })(),
            'queue_worker.restart' => self::artisan('queue:restart'),
            'queue_worker.reboot' => (function () use ($user) {
                self::artisan('queue:continue', ['queue' => config('queue.default').':default']);
                self::setState('queue_worker', 'up', $user, null);
                self::artisan('queue:restart');
                HeartbeatPing::dispatch(now()->toDateTimeString());

                return [true, 'Queue resumed, workers restarted and a ping sent.'];
            })(),
            'queue_backlog.work' => DB::table('jobs')->exists() ? self::artisan('queue:work', ['--once' => true, '--stop-when-empty' => true, '--tries' => 1]) : [true, 'Nothing waiting.'],
            'queue_backlog.clear' => (function () {
                $n = DB::table('jobs')->count();
                self::artisan('queue:clear', ['--force' => true]);

                return [true, $n.' waiting '.str('job')->plural($n).' deleted.'];
            })(),
            'failed_jobs.retry' => (function () {
                $n = DB::table('failed_jobs')->count();

                return $n ? [self::artisan('queue:retry', ['id' => ['all']])[0], $n.' failed '.str('job')->plural($n).' put back in the queue.'] : [true, 'No failed jobs.'];
            })(),
            'failed_jobs.flush' => (function () {
                $n = DB::table('failed_jobs')->count();
                self::artisan('queue:flush');

                return [true, $n.' failed '.str('job')->plural($n).' deleted.'];
            })(),
            'crons.reset' => (function () {
                $n = JobRun::where('status', 'Running')->where('started_at', '<', now()->subHour())->update(['status' => 'Failed', 'finished_at' => now(), 'message' => 'Reset from Heartbeat: stuck running over an hour.']);

                return [true, $n ? $n.' stuck '.str('run')->plural($n).' reset.' : 'Nothing stuck.'];
            })(),
            'reports.restart' => (function () {
                $stuck = ReportRun::whereIn('status', ['queued', 'running'])->where('created_at', '<', now()->subHour())->get();
                foreach ($stuck as $run) {
                    $run->update(['status' => 'queued', 'started_at' => null, 'error' => null]);
                    RunReport::dispatch($run->id);
                }

                return [true, $stuck->isEmpty() ? 'Nothing stuck.' : $stuck->count().' '.str('report')->plural($stuck->count()).' queued again.'];
            })(),
            'reports.cancel' => (function () {
                $n = ReportRun::whereIn('status', ['queued', 'running'])->where('created_at', '<', now()->subHour())->update(['status' => 'failed', 'finished_at' => now(), 'error' => 'Cancelled from Heartbeat.']);

                return [true, $n ? $n.' '.str('report')->plural($n).' cancelled.' : 'Nothing stuck.'];
            })(),
            'campaigns.send' => self::artisan('et:campaigns', ['--user' => $user?->id]),
            'tickets.notify' => (function () {
                $n = SupportTicket::active()->whereNull('assigned_to')->where('created_at', '<', now()->subHours(4))->count();
                if (! $n) {
                    return [true, 'No tickets waiting over 4 hours.'];
                }
                $managers = User::whereIn('id', RangerAgent::whereIn('level', ['manager', 'company_admin'])->where('active', true)->pluck('user_id'))->get();
                AdminNotification::notify($managers, 'ticket_new', $n.' '.str('ticket')->plural($n).' waiting over 4 hours', 'Nobody has picked them up yet.', route('ranger.tickets'));

                return [true, $managers->count().' '.str('manager')->plural($managers->count()).' notified about '.$n.' '.str('ticket')->plural($n).'.'];
            })(),
            'database.reconnect' => (function () {
                if (DB::connection()->getConfig('database') !== ':memory:') {   // an in-memory database (tests) would be lost
                    DB::purge();
                    DB::reconnect();
                }
                $t = microtime(true);
                DB::select('select 1');

                return [true, 'Reconnected. Answered in '.round((microtime(true) - $t) * 1000, 1).' ms.'];
            })(),
            'database.optimize' => DB::getDriverName() === 'sqlite' ? (function () {
                DB::statement('PRAGMA optimize');

                return [true, 'PRAGMA optimize ran.'];
            })() : [true, 'Nothing to do for '.DB::getDriverName().': the server keeps its own statistics.'],
            'cache.clear' => self::artisan('cache:clear'),
            'cache.reboot', 'website.reboot' => self::artisan('optimize:clear'),
            'storage.views' => self::artisan('view:clear'),
            'storage.link' => self::artisan('storage:link'),
            'errors.archive' => (function () {
                $log = storage_path('logs/laravel.log');
                if (! File::exists($log) || ! File::size($log)) {
                    return [true, 'The log is empty.'];
                }
                $to = storage_path('logs/laravel-'.now()->format('Y-m-d-His').'.log');
                File::move($log, $to);

                return [true, 'Archived to storage/logs/'.basename($to).'.'];
            })(),
            default => [false, 'Unknown control.'],
        };
    }
}
