<?php

namespace App\Jobs;

use App\Models\AdminNotification;
use App\Models\ReportRun;
use App\Reports\Report;
use App\Support\Export;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Runs a report in the background (the queue worker: php artisan queue:work),
 * saves it as an .xlsx and keeps the first rows for the run's page in Walker.
 */
class RunReport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public int $runId) {}

    public function handle(): void
    {
        $run = ReportRun::findOrFail($this->runId);
        $run->update(['status' => 'running', 'started_at' => now(), 'error' => null]);
        $started = microtime(true);

        $report = Report::make($run->report);
        $records = $report->query($run->params + $report->defaults());
        // A summary (counts) or every row with the report's export columns
        [$header, $rows] = $run->summary ? $report->summary($records) : $report->detail($records, $run->report);
        $rows = collect($rows)->values();
        $format = array_key_exists($run->format, Export::FORMATS) ? $run->format : 'xlsx';
        $file = 'reports/'.$run->report.($run->summary ? '-summary' : '').'-'.$run->id.'.'.Export::FORMATS[$format][1];
        $content = Export::build($format, $header, $rows, $run->title ?? $run->report);
        Storage::disk('local')->put($file, $content);

        // The first rows are kept with the run so its page can show them without running the report again
        $run->update(['status' => 'done', 'file' => $file, 'file_size' => strlen($content), 'rows' => $records->count(), 'finished_at' => now(),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'preview' => ['header' => $header, 'rows' => $rows->take(25)->map(fn ($r) => array_values((array) $r))->all()]]);
        // Walker reports are "reports done"; Corral exports are "files finished"
        AdminNotification::notify($run->user, $run->app === 'walker' ? 'report' : 'file', ($run->app === 'walker' ? 'Report ready: ' : 'Export ready: ').$run->label(),
            number_format($run->rows).' rows · '.$run->formatLabel(), route('admin.files.show', $run));
    }

    public function failed(Throwable $e): void
    {
        ReportRun::whereKey($this->runId)->update(['status' => 'failed', 'finished_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 2000)]);
        if ($run = ReportRun::find($this->runId)) {
            AdminNotification::notify($run->user, $run->app === 'walker' ? 'report' : 'file', 'Report failed: '.$run->label(), mb_substr($e->getMessage(), 0, 200), route('admin.files.show', $run));
        }
    }
}
