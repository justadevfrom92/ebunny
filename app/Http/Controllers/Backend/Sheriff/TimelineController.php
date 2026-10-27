<?php

namespace App\Http\Controllers\Backend\Sheriff;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Sheriff → Timeline: everything that ran or happened, newest first, in one list — cron jobs, ETL
 * feeds, cron automations, background reports, API calls and tests, and what people did — so you can
 * see when things worked and when they went wrong, and search the backlog for errors. ?user= shows
 * one person's timeline.
 */
class TimelineController extends Controller
{
    public const KINDS = ['job' => 'Cron Jobs', 'etl' => 'ETL Feeds', 'automation' => 'Automations', 'report' => 'Reports', 'api' => 'API Calls', 'user' => 'People'];

    public const STATUSES = ['bad' => 'Errors', 'warn' => 'Warnings', 'running' => 'Running', 'ok' => 'Worked'];

    public function index(Request $request): View
    {
        $f = $request->validate([
            'kind' => ['nullable', 'array'], 'kind.*' => ['in:'.implode(',', array_keys(self::KINDS))],
            'status' => ['nullable', 'in:'.implode(',', array_keys(self::STATUSES))],
            'user' => ['nullable', 'integer'], 'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $person = isset($f['user']) ? User::findOrFail($f['user']) : null;

        $events = DB::query()->fromSub($this->union(), 't')
            ->when($f['kind'] ?? null, fn ($q, $k) => $q->whereIn('kind', $k))
            ->when($person, fn ($q) => $q->where('user_id', $person->id))
            ->when($f['from'] ?? null, fn ($q, $d) => $q->where('at', '>=', $d))
            ->when($f['to'] ?? null, fn ($q, $d) => $q->where('at', '<', date('Y-m-d', strtotime($d.' +1 day'))))
            ->when($f['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('title', 'like', "%$s%")->orWhere('detail', 'like', "%$s%")));
        // Counts per status for the chips (before the status filter itself)
        $counts = (clone $events)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $page = $events->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('at')->orderByDesc('seq')->paginate(50)->withQueryString();

        $users = User::whereIn('id', collect($page->items())->pluck('user_id')->filter()->unique())->pluck('name', 'id');
        $jobs = config('admin.jobs');
        $feeds = config('walker.etl');
        $agents = DB::table('ai_agents')->pluck('name', 'id');
        // Errors and warnings per day over the last 14 days, for the strip above the list
        $days = DB::query()->fromSub($this->union(), 't')->where('at', '>=', now()->subDays(13)->startOfDay())
            ->when($person, fn ($q) => $q->where('user_id', $person->id))
            ->selectRaw("date(at) as d, sum(case when status = 'bad' then 1 else 0 end) as bad, sum(case when status = 'warn' then 1 else 0 end) as warn, count(*) as n")
            ->groupBy('d')->get()->keyBy('d');

        return view('backend.sheriff.timeline', compact('f', 'page', 'counts', 'person', 'users', 'jobs', 'feeds', 'agents', 'days'));
    }

    /** Every source as the same columns: at, kind, status (ok | warn | bad | running), title, detail, user_id, ref, seq (the row's ID), src (its table). */
    private function union(): Builder
    {
        $jobs = DB::table('job_runs')->selectRaw("started_at as at, case when command like 'etl:%' then 'etl' else 'job' end as kind,
            case status when 'OK' then 'ok' when 'Warning' then 'warn' when 'Skipped' then 'warn' when 'Running' then 'running' else 'bad' end as status,
            command as title, message as detail, triggered_by as user_id, command as ref, id as seq, 'job_run' as src");
        $automations = DB::table('ai_automation_runs')->selectRaw("started_at as at, 'automation' as kind,
            case status when 'ok' then 'ok' when 'running' then 'running' when 'warning' then 'warn' else 'bad' end as status,
            ai_agent_id as title, summary as detail, null as user_id, ai_agent_id as ref, id as seq, 'automation_run' as src");
        $reports = DB::table('report_runs')->selectRaw("coalesce(finished_at, started_at, created_at) as at, 'report' as kind,
            case when status = 'done' then 'ok' when status = 'failed' then 'bad' else 'running' end as status,
            coalesce(title, report) as title, case when status = 'failed' then error when status = 'done' then rows || ' rows · ' || coalesce(format, 'xlsx') else status end as detail,
            user_id, id as ref, id as seq, 'report_run' as src");
        $api = DB::table('api_logs')->selectRaw("created_at as at, 'api' as kind,
            case when status >= 500 then 'bad' when status >= 400 then 'warn' else 'ok' end as status,
            api || ' ' || action as title, 'HTTP ' || status || ' in ' || response_ms || ' ms' as detail, null as user_id, customer_id as ref, id as seq, 'api_log' as src");
        $tests = DB::table('integration_tests')->selectRaw("created_at as at, 'api' as kind, case when ok then 'ok' else 'bad' end as status,
            'Test Connection: ' || integration as title, message as detail, user_id, integration as ref, id as seq, 'integration_test' as src");
        $people = DB::table('history_items')->whereNotNull('user_id')->selectRaw("created_at as at, 'user' as kind, 'ok' as status,
            summary as title, \"group\" || ' · ' || action as detail, user_id, id as ref, id as seq, 'history_item' as src");

        return $jobs->unionAll($automations)->unionAll($reports)->unionAll($api)->unionAll($tests)->unionAll($people);
    }
}
