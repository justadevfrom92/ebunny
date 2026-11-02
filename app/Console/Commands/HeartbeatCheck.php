<?php

namespace App\Console\Commands;

use App\Jobs\HeartbeatPing;
use App\Support\Health;
use Illuminate\Console\Command;

/**
 * The heartbeat (scheduled every minute): marks the scheduler alive, sends a ping through the queue (the
 * worker marks itself alive when it runs it), then checks everything and keeps the result for Sheriff → Heartbeat.
 */
class HeartbeatCheck extends Command
{
    protected $signature = 'et:heartbeat {--strict : Exit with an error when any check is BAD}';

    protected $description = 'Heartbeat: check the scheduler, queue, jobs, crons, reports, campaigns and server';

    public function handle(): int
    {
        Health::pulse('scheduler');
        HeartbeatPing::dispatch(now()->toIso8601String());
        $beat = Health::run();
        // Every check with what it found (the same as Sheriff → Heartbeat → Checks)
        $this->table(['Check', 'Result', 'Details'], collect($beat->results)->map(fn ($r, $k) => [Health::CHECKS[$k][0] ?? $k, strtoupper($r[0]), $r[1]])->values()->all());
        $this->line('Overall: '.strtoupper($beat->status).' · checked in '.$beat->duration_ms.' ms · see Sheriff → Heartbeat for the timeline');

        // --strict: exit 1 when anything is BAD (for scripts and deploy checks); the scheduled run always succeeds
        return $this->option('strict') && $beat->status === 'bad' ? self::FAILURE : self::SUCCESS;
    }
}
