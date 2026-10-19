<?php

namespace App\Http\Controllers\Backend\Corral;

use App\Http\Controllers\Controller;
use App\Models\WorkItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Exception queues. Queue names and descriptions are in config/admin.php. */
class QueueController extends Controller
{
    public function index(): View
    {
        $counts = WorkItem::open()->selectRaw('queue, count(*) as n')->groupBy('queue')->pluck('n', 'queue');

        return view('backend.corral.queues', ['queues' => config('admin.queues'), 'counts' => $counts,
            'history' => WorkItem::whereNotNull('resolved_at')->with(['customer', 'resolver'])->latest('resolved_at')->paginate(25)->withQueryString()]);
    }

    public function show(string $queue): View
    {
        abort_unless(config()->has('admin.queues.'.$queue), 404);

        return view('backend.corral.queue', [
            'key' => $queue,
            'queue' => config('admin.queues.'.$queue),
            'items' => WorkItem::open()->where('queue', $queue)->with('customer.plan')->oldest()->get(),
            'history' => WorkItem::whereNotNull('resolved_at')->where('queue', $queue)->with(['customer', 'resolver'])->latest('resolved_at')->paginate(25)->withQueryString(),
        ]);
    }

    public function resolve(Request $request, WorkItem $item): RedirectResponse
    {
        $item->update(['resolved_at' => now(), 'resolved_by' => $request->user()->id]);

        return back()->with('status', 'Marked as fixed');
    }
}
