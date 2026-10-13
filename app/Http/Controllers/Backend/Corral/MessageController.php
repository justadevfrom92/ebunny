<?php

namespace App\Http\Controllers\Backend\Corral;

use App\Http\Controllers\Controller;
use App\Models\ContactLog;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Corral → Web Messages: what people sent through the website's Contact Us form. Each message
 * has its own page with the account it matches, a reply box (the reply is emailed and logged
 * on the account), and Mark Handled / Reopen.
 */
class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $topic = array_key_exists($request->query('topic'), ContactMessage::TOPICS) ? $request->query('topic') : null;
        $q = fn () => ContactMessage::with(['customer', 'handler'])->when($topic, fn ($x) => $x->where('topic', $topic));

        return view('backend.corral.messages', [
            'topic' => $topic,
            'counts' => ContactMessage::open()->selectRaw('topic, count(*) as n')->groupBy('topic')->pluck('n', 'topic'),
            'open' => $q()->open()->oldest()->get(),
            'handled' => $q()->whereNotNull('handled_at')->latest('handled_at')->limit(25)->get(),
        ]);
    }

    public function show(ContactMessage $message): View
    {
        $c = $message->match();

        return view('backend.corral.message', ['m' => $message->load(['handler', 'replier']), 'c' => $c,
            'others' => ContactMessage::where('id', '!=', $message->id)->where(fn ($w) => $w->whereRaw('lower(email) = ?', [strtolower($message->email)])
                ->when($c, fn ($x) => $x->orWhere('customer_id', $c->id)))->latest()->limit(5)->get()]);
    }

    public function reply(Request $request, ContactMessage $message): RedirectResponse
    {
        $data = $request->validate(['reply' => ['required', 'string', 'max:5000'], 'handled' => ['nullable', 'boolean']]);
        $c = $message->match();
        $message->update(['reply' => $data['reply'], 'replied_at' => now(), 'replied_by' => $request->user()->id, 'customer_id' => $c?->id]
            + ($request->boolean('handled') ? ['handled_at' => now(), 'handled_by' => $request->user()->id] : []));
        $ready = collect(config('admin.integrations.salesforce.env'))->every(fn ($v) => filled($v));
        if ($c) {
            ContactLog::create(['customer_id' => $c->id, 'channel' => 'Email', 'template' => 'Web Message Reply', 'body' => $data['reply'], 'status' => $ready ? 'queued' : 'not sent', 'user_id' => $request->user()->id]);
        }

        return redirect()->route('corral.messages.show', $message)->with('status', 'Reply saved'.($ready ? ' and queued to '.$message->email : ' (email is not set up, so it was logged but not sent)').'.');
    }

    public function handled(Request $request, ContactMessage $message): RedirectResponse
    {
        $message->handled_at
            ? $message->update(['handled_at' => null, 'handled_by' => null])
            : $message->update(['handled_at' => now(), 'handled_by' => $request->user()->id]);

        return back()->with('status', $message->handled_at ? 'Marked as handled' : 'Reopened');
    }
}
