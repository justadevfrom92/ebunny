<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Support\HeartbeatControl;

/** Sends Rodeo campaigns whose scheduled time has come. */
class SendCampaigns extends TrackedCommand
{
    protected $signature = 'et:campaigns {--user= : ID of the admin who ran it}';

    protected $description = 'Send scheduled marketing campaigns';

    protected function work(): array
    {
        if (HeartbeatControl::isDown('campaigns')) {
            return ['Warning', 'Campaign sending is taken down in Heartbeat: nothing sent.'];
        }
        $due = Campaign::where('status', 'scheduled')->where('scheduled_at', '<=', now())->orderBy('scheduled_at')->get();
        $sent = 0;
        foreach ($due as $c) {
            $ready = collect(config('admin.integrations.'.($c->channel === 'SMS' ? 'sms' : 'salesforce').'.env'))->every(fn ($v) => filled($v));
            [$n] = $c->send($ready);
            $sent += $n;
        }

        return ['OK', $due->isEmpty() ? 'Nothing due.' : $due->count().' '.str('campaign')->plural($due->count()).' sent, '.$sent.' messages.'];
    }
}
