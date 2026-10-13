{{-- Save icon (download the run's file) and reload icon (rerun it) for one report run in a table --}}
@include('backend.partials.report-actions', ['size' => 'row', 'downloadLabel' => 'Download .xlsx',
    'download' => $run->file ? route('walker.runs.download', $run) : null,
    'rerun' => config('walker.reports.'.$run->report) ? route('walker.runs.rerun', $run) : null,
    'why' => in_array($run->state(), ['queued', 'running'], true) ? 'still running' : ($run->state() === 'done' ? 'shown on screen, no file saved' : 'the run did not finish')])
