<?php

namespace App\Http\Controllers\Admin\Walker;

use App\Http\Controllers\Controller;
use App\Jobs\RunReport;
use App\Models\ReportRun;
use App\Reports\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Walker: reporting. Home lists every report run (from Walker and Corral) in
 * Running, Errors and Completed tables; each run has its own page with a Rerun button; the library runs any
 * report in config/walker.php in the background.
 */
class ReportController extends Controller
{
    /** The status page's tables, top to bottom: what's running now, what went wrong, what finished. */
    public const TABLES = ['running' => 'Running', 'problem' => 'Errors & Did Not Finish', 'done' => 'Completed'];

    /**
     * Report Status: three tables on one page (Running, Errors, Completed). The page asks for
     * ?part=tables every few seconds and swaps the tables in place, so runs move from Running
     * to Errors or Completed without a reload. Each table pages on its own.
     */
    public function home(Request $request): View
    {
        $tables = collect(self::TABLES)->map(fn ($label, $k) => [
            'label' => $label,
            'runs' => ReportRun::inState($k)->with('user')->latest('created_at')->latest('id')
                ->paginate($k === 'running' ? 50 : 15, ['*'], $k.'_page')->appends($request->except('part', $k.'_page')),
        ]);

        return view($request->query('part') === 'tables' ? 'admin.walker._tables' : 'admin.walker.home', ['tables' => $tables]);
    }

    public function show(ReportRun $run): View
    {
        $def = config('walker.reports.'.$run->report);

        return view('admin.walker.run', ['run' => $run->load('user'), 'def' => $def, 'columns' => $def ? Report::make($run->report)->columns() : [],
            'history' => ReportRun::where('report', $run->report)->where('id', '!=', $run->id)->with('user')->latest('id')->limit(8)->get()]);
    }

    public function rerun(Request $request, ReportRun $run): RedirectResponse
    {
        abort_unless(config('walker.reports.'.$run->report), 404, 'This report no longer exists.');
        $new = $this->queue($request, $run->report, $run->params ?? [], $run->id);

        return redirect()->route('walker.runs.show', $new)->with('status', 'Report #'.$run->id.' is running again as #'.$new->id.'.');
    }

    public function download(ReportRun $run): StreamedResponse
    {
        abort_unless($run->file && Storage::disk('local')->exists($run->file), 404);

        return Storage::disk('local')->download($run->file, basename($run->file));
    }

    // ---------- Library ----------

    public function index(): View
    {
        $last = ReportRun::selectRaw('report, max(created_at) as last_at, count(*) as runs')->groupBy('report')->get()->keyBy('report');

        return view('admin.walker.reports', ['groups' => collect(config('walker.reports'))->map(fn ($d, $k) => $d + ['key' => $k])->groupBy('category'), 'last' => $last]);
    }

    public function report(string $key): View
    {
        $report = Report::make($key);

        return view('admin.walker.report', ['def' => Report::definition($key), 'filters' => $report->filters(), 'defaults' => $report->defaults(),
            'columns' => $report->columns(), 'runs' => ReportRun::where('report', $key)->with('user')->latest('id')->limit(10)->get()]);
    }

    public function run(Request $request, string $key): RedirectResponse
    {
        $report = Report::make($key);
        $f = array_merge($report->defaults(), array_filter($request->validate($report->rules()), fn ($v) => $v !== null && $v !== []));
        $run = $this->queue($request, $key, $f);

        return redirect()->route('walker.runs.show', $run)->with('status', 'Report queued.');
    }

    private function queue(Request $request, string $key, array $params, ?int $rerunOf = null): ReportRun
    {
        $def = Report::definition($key);
        $run = ReportRun::create(['user_id' => $request->user()->id, 'app' => 'walker', 'report' => $key, 'title' => $def['title'], 'model' => $def['model'],
            'params' => collect($params)->except('output')->all(), 'rows' => 0, 'status' => 'queued', 'created_at' => now(), 'rerun_of' => $rerunOf]);
        RunReport::dispatch($run->id);

        return $run->fresh();
    }
}
