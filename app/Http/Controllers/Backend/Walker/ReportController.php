<?php

namespace App\Http\Controllers\Backend\Walker;

use App\Http\Controllers\Controller;
use App\Jobs\RunReport;
use App\Models\ReportColumn;
use App\Models\ReportRun;
use App\Reports\Report;
use App\Support\Export;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

        return view($request->query('part') === 'tables' ? 'backend.walker._tables' : 'backend.walker.home', ['tables' => $tables]);
    }

    public function show(ReportRun $run): View
    {
        $def = config('walker.reports.'.$run->report);

        return view('backend.walker.run', ['run' => $run->load('user'), 'def' => $def, 'columns' => $def ? Report::make($run->report)->columns() : [],
            'history' => ReportRun::where('report', $run->report)->where('id', '!=', $run->id)->with('user')->latest('id')->limit(8)->get()]);
    }

    public function rerun(Request $request, ReportRun $run): RedirectResponse
    {
        abort_unless(config('walker.reports.'.$run->report), 404, 'This report no longer exists.');
        $new = $this->queue($request, $run->report, $run->params ?? [], $run->id, $run->format ?? 'xlsx', (bool) $run->summary);

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

        return view('backend.walker.reports', ['groups' => collect(config('walker.reports'))->map(fn ($d, $k) => $d + ['key' => $k])->groupBy('category'), 'last' => $last]);
    }

    public function report(string $key): View
    {
        $report = Report::make($key);

        $saved = ReportColumn::where('report', $key)->first();

        return view('backend.walker.report', ['def' => Report::definition($key), 'filters' => $report->filters(), 'defaults' => $report->defaults(),
            'columns' => $report->columns(), 'export' => $saved?->columns ?? [], 'exportBy' => $saved?->user, 'exportAt' => $saved?->updated_at,
            'fields' => $report->fields(), 'runs' => ReportRun::where('report', $key)->with('user')->latest('id')->limit(10)->get()]);
    }

    public function run(Request $request, string $key): RedirectResponse
    {
        $report = Report::make($key);
        $opts = $request->validate(['format' => ['nullable', Rule::in(array_keys(Export::FORMATS))], 'summary' => ['nullable', 'boolean']]);
        $f = array_merge($report->defaults(), array_filter($request->validate($report->rules()), fn ($v) => $v !== null && $v !== []));
        $run = $this->queue($request, $key, $f, null, $opts['format'] ?? 'xlsx', (bool) ($opts['summary'] ?? false));

        return redirect()->route('walker.runs.show', $run)->with('status', $run->label().' is running in the background. You\'ll get a notice at the top right when the file is ready.');
    }

    /**
     * Export Columns: which model keys go in the file, the title each column gets, and their order.
     * Saved as a list of [key, title]; "key_array" takes the same as a nested key => title array
     * (e.g. {"customer": {"name": "Name"}, "status": "Status"}) and flattens it in order.
     */
    public function saveColumns(Request $request, string $key): RedirectResponse
    {
        Report::make($key);
        if ($request->boolean('reset')) {
            ReportColumn::where('report', $key)->delete();

            return back()->with('status', 'Export columns reset to the report\'s own columns.');
        }
        if ($request->filled('key_array')) {
            $nested = json_decode((string) $request->input('key_array'), true);
            if (! is_array($nested) || ! $nested) {
                return back()->withErrors(['key_array' => 'That isn\'t a key array. Use JSON like {"customer": {"name": "Name"}, "status": "Status"}.'])->withInput();
            }
            $cols = Report::flattenColumns($nested);
        } else {
            $data = $request->validate(['cols' => ['required', 'array', 'min:1'], 'cols.*.key' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9_.*]+$/'],
                'cols.*.title' => ['nullable', 'string', 'max:80'], 'cols.*.order' => ['nullable', 'integer']]);
            $cols = collect($data['cols'])->filter(fn ($c) => ! empty($c['key']))->sortBy(fn ($c) => (int) ($c['order'] ?? 0))
                ->map(fn ($c) => ['key' => $c['key'], 'title' => ($c['title'] ?? '') !== '' ? $c['title'] : $c['key']])->values()->all();
        }
        abort_if(! $cols, 422, 'Add at least one column.');
        ReportColumn::updateOrCreate(['report' => $key], ['columns' => $cols, 'user_id' => $request->user()->id]);

        return back()->with('status', count($cols).' export '.str('column')->plural(count($cols)).' saved. Every run of this report, from Walker or Corral, uses them.');
    }

    private function queue(Request $request, string $key, array $params, ?int $rerunOf = null, string $format = 'xlsx', bool $summary = false): ReportRun
    {
        $def = Report::definition($key);
        $run = ReportRun::create(['user_id' => $request->user()->id, 'app' => 'walker', 'report' => $key, 'title' => $def['title'], 'model' => $def['model'],
            'params' => collect($params)->except(['output', 'format', 'summary'])->all(), 'format' => $format, 'summary' => $summary,
            'rows' => 0, 'status' => 'queued', 'created_at' => now(), 'rerun_of' => $rerunOf]);
        RunReport::dispatch($run->id);

        return $run->fresh();
    }
}
