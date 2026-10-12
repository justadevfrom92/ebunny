<?php

namespace App\Http\Controllers\Backend\Corral;

use App\Http\Controllers\Controller;
use App\Jobs\RunReport;
use App\Models\ReportRun;
use App\Models\User;
use App\Reports\Report;
use App\Support\Xlsx;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Corral → Reports (Orders, Notes, Phonecalls). Each shows on screen or as a
 * summary, downloads as CSV or XLS, or runs in the backend. The queries are the
 * report classes in app/Reports, which Walker uses too; every run is listed in Walker.
 */
class ReportController extends Controller
{
    /** The original's output choices. "backend" queues the run and saves an .xlsx under Recent Results. */
    private const OUTPUTS = ['screen', 'summary', 'csv', 'csv-summary', 'xls', 'xls-summary', 'backend'];

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
        $f = $request->validate($report->rules() + ['output' => ['nullable', Rule::in(self::OUTPUTS)]]);
        $f = array_merge($report->defaults(), array_filter($f, fn ($v) => $v !== null)) + ['output' => 'screen'];

        $records = null;
        $summary = null;
        if ($request->has('run')) {
            if ($response = $this->deliver($request, $key, $report, $f)) {
                return $response;
            }
            $records = $report->query($f);
            $summary = $report->summary($records);
            $this->remember($request, $key, $f, $records->count());
        }

        return view($view, ['f' => $f, 'records' => $records, 'summary' => $summary, 'recent' => $this->recent($request, $key)] + $extra($report));
    }

    /** File outputs download now; Backend is queued. Returns null for the on-screen outputs. */
    private function deliver(Request $request, string $key, Report $report, array $f)
    {
        $output = $f['output'];
        if (in_array($output, ['screen', 'summary'], true)) {
            return null;
        }
        if ($output === 'backend') {
            $run = $this->remember($request, $key, $f, 0, 'queued');
            RunReport::dispatch($run->id);

            return back()->with('status', 'Report queued. It appears under Recent Results (and in Walker) with a download link when it finishes.');
        }

        $records = $report->query($f);
        $this->remember($request, $key, $f, $records->count());
        $isSummary = str_ends_with($output, 'summary');
        [$header, $data] = $isSummary ? $report->summary($records) : $report->detail($records);
        $name = $key.($isSummary ? '-summary' : '').'-'.($f['start'] ?? today()->toDateString()).'-to-'.($f['end'] ?? today()->toDateString());

        return str_starts_with($output, 'xls')
            ? response(Xlsx::build($header, $data, ucfirst($key)), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$name.'.xlsx"',
            ])
            : $this->csv($name, $header, $data);
    }

    /** Recent Results → download a Backend report (your own runs only). */
    public function download(Request $request, ReportRun $run)
    {
        abort_unless($run->user_id === $request->user()->id && $run->file && Storage::disk('local')->exists($run->file), 404);

        return Storage::disk('local')->download($run->file, basename($run->file));
    }

    private function remember(Request $request, string $key, array $f, int $rows, string $status = 'done'): ReportRun
    {
        $def = Report::definition($key);

        return ReportRun::create(['user_id' => $request->user()->id, 'app' => 'corral', 'report' => $key, 'title' => $def['title'], 'model' => $def['model'],
            'params' => array_filter($f, fn ($v) => $v !== null && $v !== []), 'rows' => $rows, 'status' => $status, 'created_at' => now(),
            'started_at' => $status === 'done' ? now() : null, 'finished_at' => $status === 'done' ? now() : null]);
    }

    private function recent(Request $request, string $report): Collection
    {
        return ReportRun::where('user_id', $request->user()->id)->where('report', $report)->latest('created_at')->latest('id')->limit(10)->get();
    }

    private function csv(string $name, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);
            foreach ($rows as $row) {
                // Keep spreadsheet apps from running cell text as a formula
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, $row));
            }
            fclose($out);
        }, $name.'.csv', ['Content-Type' => 'text/csv']);
    }
}
