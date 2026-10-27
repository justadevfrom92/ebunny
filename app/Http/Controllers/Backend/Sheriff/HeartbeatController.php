<?php

namespace App\Http\Controllers\Backend\Sheriff;

use App\Http\Controllers\Controller;
use App\Models\Heartbeat;
use App\Models\HistoryItem;
use App\Models\JobRun;
use App\Support\Health;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Sheriff → Heartbeat: the overall health of the admin's background work right now (scheduler, queue worker,
 * jobs, crons, reports, campaigns, tickets, server), the last 24 hours, what's waiting and failed, and the
 * latest actions people took. The page refreshes itself every 30 seconds.
 */
class HeartbeatController extends Controller
{
    public function index(): View
    {
        $last = Heartbeat::latest('checked_at')->latest('id')->first();
        $now = Health::checks();   // always fresh on the page
        $since = now()->subDay();
        $beats = Heartbeat::where('checked_at', '>=', $since)->orderBy('checked_at')->get(['checked_at', 'status']);
        // 96 blocks of 15 minutes: the worst status in each (gray when no check ran)
        $blocks = collect(range(95, 0))->map(function ($i) use ($beats) {
            $from = now()->subMinutes(($i + 1) * 15);
            $in = $beats->filter(fn ($b) => $b->checked_at->gt($from) && $b->checked_at->lte($from->copy()->addMinutes(15)));

            return ['from' => $from, 'n' => $in->count(), 'status' => $in->isEmpty() ? 'none' : Health::overall($in->map(fn ($b) => [$b->status])->all())];
        });

        return view('backend.sheriff.heartbeat', [
            'checks' => $now, 'status' => Health::overall($now), 'last' => $last, 'blocks' => $blocks,
            'uptime' => $beats->count() ? round(100 * $beats->where('status', '!=', 'bad')->count() / $beats->count(), 1) : null,
            'beats24' => $beats->count(), 'scheduler' => Health::lastPulse('scheduler'), 'queue' => Health::lastPulse('queue'),
            'waiting' => DB::table('jobs')->orderBy('id')->limit(10)->get(), 'waitingCount' => DB::table('jobs')->count(),
            'failed' => DB::table('failed_jobs')->latest('failed_at')->limit(10)->get(), 'failedCount' => DB::table('failed_jobs')->where('failed_at', '>=', $since)->count(),
            'crons' => collect(config('admin.jobs'))->map(fn ($job, $command) => ['command' => $command, 'name' => $job[0], 'schedule' => $job[1], 'last' => JobRun::where('command', $command)->latest('started_at')->first()]),
            'actions' => HistoryItem::with(['user', 'customer'])->latest('created_at')->latest('id')->limit(10)->get(),
        ]);
    }

    public function check(): RedirectResponse
    {
        $beat = Health::run();

        return back()->with('status', 'Checked: '.['ok' => 'all healthy', 'warn' => 'some warnings', 'bad' => 'problems found'][$beat->status]);
    }
}
