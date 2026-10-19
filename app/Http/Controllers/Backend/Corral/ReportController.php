<?php

namespace App\Http\Controllers\Backend\Corral;

use App\Http\Controllers\Controller;
use App\Jobs\RunReport;
use App\Models\ReportRun;
use App\Models\User;
use App\Reports\Report;
use App\Support\Export;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Corral → Reports (Orders, Notes, Phonecalls). Run Report always runs in the background and saves a
 * file in the format chosen (Excel, CSV, tab-separated or JSON; full rows or a summary). The page lists
 * your runs on the right; you get a notice (top right) when each one is ready. The queries are the report
 * classes in app/Reports, which Walker uses too.
 */
class ReportController extends Controller
{
    public function orders(Request $request)
    {
        return $this->report($request, 'orders', 'backend.corral.report', fn ($report) => ['statuses' => array_keys(config('admin.customer_statuses'))]);
    }

    public function notes(Request $request)
    {
        return $this->report($request, 'notes', 'backend.corral.reports.notes', fn ($report) => [
            'priorities' => [...config('corral.priorities'), 'System'], 'users' => User::orderBy('name')->get(['id', 'name'])]);
    }

    public function phonecalls(Request $request)
    {
        return $this->report($request, 'phonecalls', 'backend.corral.reports.phonecalls', fn ($report) => []);
    }

    private function report(Request $request, string $key, string $view, \Closure $extra)
    {
        $report = Report::make($key);
        $f = $request->validate($report->rules() + ['format' => ['nullable', Rule::in(array_keys(Export::FORMATS))], 'summary' => ['nullable', 'boolean']]);
        $f = array_merge($report->defaults(), array_filter($f, fn ($v) => $v !== null)) + ['format' => 'xlsx', 'summary' => false];

        if ($request->has('run')) {
            $run = $this->remember($request, $key, $f);
            RunReport::dispatch($run->id);

            return redirect()->to(url()->current().'?'.http_build_query(collect($f)->except('summary')->filter(fn ($v) => $v !== null && $v !== '' && $v !== [])->all()))
                ->with('status', $run->label().' is running in the background. You\'ll get a notice at the top right when the file is ready.');
        }

        return view($view, ['f' => $f, 'recent' => $this->recent($request, $key), 'def' => Report::definition($key)] + $extra($report));
    }

    /** Download a finished run (your own runs only). */
    public function download(Request $request, ReportRun $run)
    {
        abort_unless($run->user_id === $request->user()->id && $run->file && Storage::disk('local')->exists($run->file), 404);
        $run->forceFill(['seen_at' => $run->seen_at ?? now()])->save();

        return Storage::disk('local')->download($run->file, basename($run->file));
    }

    private function remember(Request $request, string $key, array $f): ReportRun
    {
        $def = Report::definition($key);

        return ReportRun::create(['user_id' => $request->user()->id, 'app' => 'corral', 'report' => $key, 'title' => $def['title'], 'model' => $def['model'],
            'params' => collect($f)->except(['format', 'summary', 'output'])->filter(fn ($v) => $v !== null && $v !== [] && $v !== '')->all(),
            'format' => $f['format'], 'summary' => (bool) $f['summary'], 'rows' => 0, 'status' => 'queued', 'created_at' => now()]);
    }

    private function recent(Request $request, string $report): Collection
    {
        return ReportRun::where('user_id', $request->user()->id)->where('report', $report)->latest('created_at')->latest('id')->limit(10)->get();
    }
}
