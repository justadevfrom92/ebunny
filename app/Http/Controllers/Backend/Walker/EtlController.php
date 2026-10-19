<?php

namespace App\Http\Controllers\Backend\Walker;

use App\Http\Controllers\Controller;
use App\Models\JobRun;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Walker → ETL Feeds: files that come in from, or go out to, other companies on a schedule
 * (config('walker.etl')). Each feed's runs are job runs with the command "etl:<key>".
 */
class EtlController extends Controller
{
    public function index(): View
    {
        $commands = collect(array_keys(config('walker.etl')))->map(fn ($k) => 'etl:'.$k);
        $last = JobRun::whereIn('command', $commands)->whereIn('id', JobRun::selectRaw('max(id)')->whereIn('command', $commands)->groupBy('command'))->get()->keyBy('command');
        $week = JobRun::whereIn('command', $commands)->where('started_at', '>=', now()->subDays(7))
            ->selectRaw("command, count(*) as runs, sum(case when status = 'Failed' then 1 else 0 end) as failed, sum(case when status = 'Warning' then 1 else 0 end) as warned")
            ->groupBy('command')->get()->keyBy('command');

        return view('backend.walker.etl', ['feeds' => config('walker.etl'), 'last' => $last, 'week' => $week]);
    }

    public function show(Request $request, string $feed): View
    {
        $def = config('walker.etl.'.$feed);
        abort_unless($def, 404);
        $f = $request->validate(['status' => ['nullable', 'in:OK,Warning,Failed']]);
        $all = JobRun::where('command', 'etl:'.$feed);

        return view('backend.walker.etl-feed', ['key' => $feed, 'feed' => $def, 'f' => $f,
            'runs' => (clone $all)->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->latest('started_at')->latest('id')->paginate(25)->withQueryString(),
            'counts' => (clone $all)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'lastOk' => (clone $all)->where('status', 'OK')->latest('started_at')->first()]);
    }
}
