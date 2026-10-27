<?php

namespace App\Jobs;

use App\Support\Health;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** A tiny job the heartbeat puts on the queue each minute; when the worker runs it, the worker is alive. */
class HeartbeatPing implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public string $sentAt) {}

    public function handle(): void
    {
        // How long it waited in the queue tells how far behind the worker is
        Health::pulse('queue', ['waited_s' => max(0, now()->getTimestamp() - (int) strtotime($this->sentAt))]);
    }
}
