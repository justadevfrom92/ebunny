<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Jobs\RunReport;
use App\Models\ReportRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * My Files (from the user menu, top right): every report you ran, from any app, with its file.
 * Reports run in the background; the number by your name counts finished ones you haven't seen,
 * and /admin/notifications feeds the "your report is ready" popup.
 */
class FilesController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate(['status' => ['nullable', 'in:running,done,failed']]);
        $mine = ReportRun::where('user_id', $request->user()->id);
        $runs = (clone $mine)->when($f['status'] ?? null, fn ($q, $s) => $s === 'running' ? $q->whereIn('status', ['queued', 'running']) : $q->where('status', $s))
            ->latest('created_at')->latest('id')->paginate(25)->withQueryString();
        $unseen = (clone $mine)->unseenFor($request->user()->id)->pluck('id')->all();
        // Opening the list counts as seeing them
        (clone $mine)->unseenFor($request->user()->id)->update(['seen_at' => now()]);

        return view('backend.files.index', ['runs' => $runs, 'unseen' => $unseen, 'f' => $f,
            'counts' => ['all' => (clone $mine)->count(), 'running' => (clone $mine)->whereIn('status', ['queued', 'running'])->count(),
                'done' => (clone $mine)->where('status', 'done')->count(), 'failed' => (clone $mine)->where('status', 'failed')->count()]]);
    }

    /** One file: what it is, the settings it ran with, and its first rows (kept with the run, so nothing runs again). */
    public function show(Request $request, ReportRun $run): View
    {
        $this->own($request, $run);
        $request->attributes->set('menu_app', $run->app);   // the sidebar of the app the report came from
        if (in_array($run->status, ['done', 'failed'], true) && ! $run->seen_at) {
            $run->forceFill(['seen_at' => now()])->save();
        }

        return view('backend.files.show', ['run' => $run, 'def' => config('walker.reports.'.$run->report)]);
    }

    public function download(Request $request, ReportRun $run): StreamedResponse
    {
        $this->own($request, $run);
        abort_unless($run->file && Storage::disk('local')->exists($run->file), 404);
        $run->forceFill(['seen_at' => $run->seen_at ?? now()])->save();

        return Storage::disk('local')->download($run->file, basename($run->file));
    }

    /** Run it again with the same settings and format. */
    public function rerun(Request $request, ReportRun $run): RedirectResponse
    {
        $this->own($request, $run);
        abort_unless(config('walker.reports.'.$run->report), 404);
        $new = $run->replicate(['status', 'file', 'file_size', 'rows', 'started_at', 'finished_at', 'duration_ms', 'error', 'preview', 'seen_at']);
        $new->fill(['status' => 'queued', 'rows' => 0, 'created_at' => now(), 'rerun_of' => $run->id])->save();
        RunReport::dispatch($new->id);

        return redirect()->route('admin.files.show', $new)->with('status', $new->label().' is running again. You\'ll get a notice when it\'s ready.');
    }

    public function seen(Request $request): RedirectResponse
    {
        ReportRun::unseenFor($request->user()->id)->update(['seen_at' => now()]);

        return back();
    }

    /** For the popup and the number by your name: finished runs you haven't seen, newest first. */
    public function notifications(Request $request): JsonResponse
    {
        $items = ReportRun::unseenFor($request->user()->id)->latest('finished_at')->limit(10)->get();

        return response()->json([
            'count' => ReportRun::unseenFor($request->user()->id)->count(),
            'running' => ReportRun::inState('running')->where('user_id', $request->user()->id)->count(),
            'items' => $items->map(fn ($r) => ['id' => $r->id, 'title' => $r->label(), 'ok' => $r->status === 'done', 'rows' => $r->rows, 'format' => $r->formatLabel(),
                'url' => route('admin.files.show', $r), 'download' => $r->file ? route('admin.files.download', $r) : null])->values(),
        ]);
    }

    private function own(Request $request, ReportRun $run): void
    {
        abort_unless($run->user_id === $request->user()->id || $request->user()->hasPerm('walker'), 404);
    }
}
