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
    protected $signature = 'et:heartbeat';

    protected $description = 'Heartbeat: check the scheduler, queue, jobs, crons, reports, campaigns and server';

    public function handle(): int
    {
        Health::pulse('scheduler');
        HeartbeatPing::dispatch(now()->toIso8601String());
        $beat = Health::run();
        $this->line(strtoupper($beat->status).': '.collect($beat->results)->filter(fn ($r) => $r[0] !== 'ok')->keys()->implode(', '));

        return self::SUCCESS;
    }
}
