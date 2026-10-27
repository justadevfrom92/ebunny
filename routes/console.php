<?php

use App\Support\HeartbeatControl;
use Illuminate\Support\Facades\Schedule;

/*
| Scheduled jobs come from config/admin.php (Sheriff → Crons shows them).
| On the Linux server add one cron entry:
|   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
*/
foreach (config('admin.jobs') as $command => [$name, $cron]) {
    // Skipped while the scheduler or the crons are taken down in Sheriff → Heartbeat
    Schedule::command($command)->cron($cron)->withoutOverlapping()->description($name)
        ->skip(fn () => HeartbeatControl::isDown('scheduler') || HeartbeatControl::isDown('crons'));
}

// The heartbeat: every minute it checks the scheduler, queue worker, jobs, crons, reports and server (Sheriff → Heartbeat)
Schedule::command('et:heartbeat')->everyMinute()->withoutOverlapping()->description('Heartbeat health check');
