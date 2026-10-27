<?php

namespace App\Http\Controllers\Backend\Sheriff;

use App\Http\Controllers\Backend\Lando\DashboardController;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Heartbeat;
use App\Models\HeartbeatAction;
use App\Models\HistoryItem;
use App\Models\JobRun;
use App\Models\ReportRun;
use App\Models\SupportTicket;
use App\Support\Health;
use App\Support\HeartbeatControl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Sheriff → Heartbeat: the overall health of the admin's background work right now (scheduler, queue worker,
 * jobs, crons, reports, campaigns, tickets, server), the last 24 hours, what's waiting and failed, and the
 * latest actions people took. The page refreshes itself every 30 seconds. Each check has its own page with its
 * history and its controls (restart, reboot, take down, bring up…), and the Control Log lists every control used.
 */
class HeartbeatController extends Controller
{
    public function index(): View
    {
        $last = Heartbeat::latest('checked_at')->latest('id')->first();
        $now = Health::checks();   // always fresh on the page
        $since = now()->subDay();
        $beats = Heartbeat::where('checked_at', '>=', $since)->orderBy('checked_at')->get(['checked_at', 'status', 'results']);
        $used = HeartbeatAction::with('user')->where('created_at', '>=', $since)->get();
        // 96 blocks of 15 minutes: the worst status in each (gray when no check ran), and what was wrong in it (shown when a block is clicked)
        $blocks = collect(range(95, 0))->map(function ($i) use ($beats, $used) {
            $from = now()->subMinutes(($i + 1) * 15);
            $to = $from->copy()->addMinutes(15);
            $in = $beats->filter(fn ($b) => $b->checked_at->gt($from) && $b->checked_at->lte($to));
            $rows = [];
            foreach (Health::CHECKS as $key => [$name]) {
                $off = $in->filter(fn ($b) => ($b->results[$key][0] ?? 'ok') !== 'ok');
                if ($off->isNotEmpty()) {
                    $worst = collect(['bad', 'warn', 'down'])->first(fn ($st) => $off->contains(fn ($b) => $b->results[$key][0] === $st));
                    $rows[] = [$name, $worst, $off->last(fn ($b) => $b->results[$key][0] === $worst)->results[$key][1] ?? '', $off->count(), route('sheriff.heartbeat.show', $key)];
                }
            }

            return ['from' => $from, 'n' => $in->count(), 'status' => $in->isEmpty() ? 'none' : Health::overall($in->map(fn ($b) => [$b->status])->all()),
                'rows' => $rows, 'actions' => self::actionsIn($used, $from, $to, true)];
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

    /** Controls used between two times: [time, check, control, who, ok]. */
    private static function actionsIn($used, $from, $to, bool $withCheck): array
    {
        return $used->filter(fn ($a) => $a->created_at->gt($from) && $a->created_at->lte($to))->sortBy('created_at')
            ->map(fn ($a) => [$a->created_at->format('g:i A'), $withCheck ? $a->checkName() : null, $a->label(), $a->user?->name ?? 'System', $a->ok])->values()->all();
    }

    /** Every check, now, with whether it's taken down. Each row opens the check's page. */
    public function checks(): View
    {
        return view('backend.sheriff.heartbeat-checks', ['checks' => Health::checks(), 'states' => HeartbeatControl::states(),
            'lastAction' => HeartbeatAction::with('user')->latest('created_at')->latest('id')->get()->unique('check_key')->keyBy('check_key')]);
    }

    /** One check: now, the last 24 hours, its changes, what it's looking at, its controls and what's been done to it. */
    public function show(Request $request, string $key): View
    {
        abort_unless(isset(Health::CHECKS[$key]), 404);
        $now = Health::checks()[$key];
        $since = now()->subDay();
        $beats = Heartbeat::where('checked_at', '>=', $since)->orderBy('checked_at')->get(['checked_at', 'results']);
        $used = HeartbeatAction::with('user')->where('check_key', $key)->where('created_at', '>=', $since)->get();
        $blocks = collect(range(95, 0))->map(function ($i) use ($beats, $key, $used) {
            $from = now()->subMinutes(($i + 1) * 15);
            $in = $beats->filter(fn ($b) => $b->checked_at->gt($from) && $b->checked_at->lte($from->copy()->addMinutes(15)));

            $states = $in->map(fn ($b) => $b->results[$key][0] ?? 'ok');
            // Each run of the same result in the block: [from, to, state, detail, checks]
            $runs = [];
            foreach ($in as $b) {
                [$st, $detail] = $b->results[$key] ?? ['ok', ''];
                $last = count($runs) - 1;
                if ($last >= 0 && $runs[$last][2] === $st && $runs[$last][3] === $detail) {
                    $runs[$last][1] = $b->checked_at->format('g:i A');
                    $runs[$last][4]++;
                } else {
                    $runs[] = [$b->checked_at->format('g:i A'), $b->checked_at->format('g:i A'), $st, $detail, 1];
                }
            }

            return ['from' => $from, 'n' => $in->count(), 'status' => $in->isEmpty() ? 'none' : collect(['bad', 'warn', 'down', 'ok'])->first(fn ($s) => $states->contains($s)),
                'rows' => $runs, 'actions' => self::actionsIn($used, $from, $from->copy()->addMinutes(15), false)];
        });
        // Each time the result changed (newest first)
        $changes = [];
        $prev = null;
        foreach ($beats as $b) {
            $r = $b->results[$key] ?? ['ok', ''];
            if ($prev === null || $r[0] !== $prev) {
                $changes[] = ['at' => $b->checked_at, 'from' => $prev, 'to' => $r[0], 'detail' => $r[1] ?? ''];
            }
            $prev = $r[0];
        }
        $changes = collect(array_reverse($changes));
        $page = max(1, (int) $request->query('changes', 1));
        $changesPage = new LengthAwarePaginator($changes->forPage($page, 10)->values(), $changes->count(), 10, $page, ['path' => $request->url(), 'pageName' => 'changes']);
        $healthy = $beats->count() ? round(100 * $beats->filter(fn ($b) => ($b->results[$key][0] ?? 'ok') !== 'bad')->count() / $beats->count(), 1) : null;

        return view('backend.sheriff.heartbeat-check', [
            'key' => $key, 'check' => Health::CHECKS[$key], 'now' => $now, 'blocks' => $blocks, 'changes' => $changesPage, 'healthy' => $healthy, 'beats24' => $beats->count(),
            'control' => HeartbeatControl::states()[$key] ?? null, 'actions' => HeartbeatControl::actions($key), 'downable' => HeartbeatControl::DOWNABLE[$key] ?? null,
            'details' => $this->details($key),
            'log' => HeartbeatAction::with('user')->where('check_key', $key)->latest('created_at')->latest('id')->paginate(10, ['*'], 'log')->withQueryString(),
        ]);
    }

    /** Use one control on a check. */
    public function control(Request $request, string $key, string $action): RedirectResponse
    {
        abort_unless($request->user()->can('configure'), 403);
        abort_unless(isset(Health::CHECKS[$key], HeartbeatControl::actions($key)[$action]), 404);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        [$ok, $out] = HeartbeatControl::perform($key, $action, $request->user(), $data['reason'] ?? null);
        $label = HeartbeatControl::actions($key)[$action][0];

        return redirect()->route('sheriff.heartbeat.show', $key)->with($ok ? 'status' : 'error', Health::CHECKS[$key][0].' · '.$label.': '.Str::limit(strtok($out, "\n"), 160));
    }

    /** Every control anyone has used, newest first. */
    public function log(Request $request): View
    {
        $q = HeartbeatAction::with('user')->latest('created_at')->latest('id');
        if (isset(Health::CHECKS[$request->query('check')])) {
            $q->where('check_key', $request->query('check'));
        }

        return view('backend.sheriff.heartbeat-log', ['log' => $q->paginate(10)->withQueryString(), 'check' => $request->query('check')]);
    }

    /**
     * What the check is looking at, as a small table: [title, columns, rows].
     *
     * @return array{0: string, 1: array<int, string>, 2: array<int, array<int, string>>}
     */
    private function details(string $key): array
    {
        $when = fn ($t) => $t ? Carbon::parse($t)->format('n/j/Y g:i A') : '—';
        $job = fn ($payload) => class_basename(json_decode($payload, true)['displayName'] ?? 'Job');
        $crons = fn () => collect(config('admin.jobs'))->map(function ($j, $command) use ($when) {
            $last = JobRun::where('command', $command)->latest('started_at')->first();

            return [$j[0], $command, $j[1], $when($last?->started_at), $last?->status ?? '—'];
        })->values()->all();
        $site = function (array $names) {
            try {
                $c = collect(DashboardController::checks());
            } catch (Throwable) {
                $c = collect();
            }

            return $c->filter(fn ($r) => in_array($r['name'], $names, true) || ($names === ['*settings'] && $r['group'] === 'Settings'))
                ->map(fn ($r) => [$r['name'], ['ok' => 'Healthy', 'warn' => 'Warning', 'bad' => 'Problem'][$r['state']] ?? $r['state'], $r['found'], $r['fix'] ?? '—'])->values()->all();
        };

        return match ($key) {
            'scheduler', 'crons' => ['Crons', ['Cron', 'Command', 'Runs', 'Last Run', 'Result'], $crons()],
            'queue_worker' => ['Pings', ['Pulse', 'Last', 'Detail'], DB::table('heartbeat_pulses')->get()->map(fn ($p) => [ucfirst($p->key), $when($p->beat_at),
                collect(json_decode((string) $p->meta, true) ?: [])->map(fn ($v, $k) => $k === 'waited_s' ? 'waited '.$v.' s in the queue' : $k.': '.$v)->implode(' · ') ?: '—'])->all()],
            'queue_backlog' => ['Waiting in the Queue', ['Job', 'Queue', 'Tries', 'Waiting Since'], DB::table('jobs')->orderBy('id')->limit(10)->get()
                ->map(fn ($j) => [$job($j->payload), $j->queue, (string) $j->attempts, $when(Carbon::createFromTimestamp($j->available_at))])->all()],
            'failed_jobs' => ['Failed Jobs', ['Failed', 'Job', 'Error'], DB::table('failed_jobs')->latest('failed_at')->limit(10)->get()
                ->map(fn ($j) => [$when($j->failed_at), $job($j->payload), Str::limit(strtok((string) $j->exception, "\n"), 160)])->all()],
            'reports' => ['Reports Waiting or Failed', ['Report', 'Status', 'Started', 'Error'], ReportRun::whereIn('status', ['queued', 'running', 'failed'])->latest('created_at')->limit(10)->get()
                ->map(fn ($r) => [$r->label(), ucfirst($r->status), $when($r->created_at), Str::limit((string) $r->error, 120) ?: '—'])->all()],
            'campaigns' => ['Scheduled Campaigns', ['Campaign', 'Channel', 'Scheduled For', 'Status'], Campaign::where('status', 'scheduled')->orderBy('scheduled_at')->limit(10)->get()
                ->map(fn ($c) => [$c->name, $c->channel, $when($c->scheduled_at), $c->scheduled_at?->isPast() ? 'Late' : 'Waiting'])->all()],
            'tickets' => ['Waiting With Nobody on Them', ['Ticket', 'Subject', 'Opened', 'Priority'], SupportTicket::active()->whereNull('assigned_to')->orderBy('created_at')->limit(10)->get()
                ->map(fn ($t) => ['#'.$t->id, Str::limit((string) $t->subject, 60), $when($t->created_at), (string) $t->priority])->all()],
            'database' => ['Database', ['Check', 'Status', 'Found', 'What to Do'], $site(['Database'])],
            'cache' => ['Cache', ['Check', 'Status', 'Found', 'What to Do'], $site(['Cache'])],
            'storage' => ['Storage', ['Check', 'Status', 'Found', 'What to Do'], $site(['Storage', 'Disk space'])],
            'errors' => ['Errors', ['Check', 'Status', 'Found', 'What to Do'], $site(['Errors in the last 24 hours'])],
            'website' => ['Website Settings', ['Setting', 'Status', 'Found', 'What to Do'], $site(['*settings'])],
            default => ['Details', [], []],
        };
    }
}
