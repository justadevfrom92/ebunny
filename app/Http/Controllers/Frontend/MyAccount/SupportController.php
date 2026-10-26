<?php

namespace App\Http\Controllers\Frontend\MyAccount;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Customer;
use App\Models\RangerAgent;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * My Account → Support Tickets: open a ticket, follow the conversation, reply, and once it's solved
 * say whether we helped (the approval rating Ranger reports on). Internal notes are never shown here.
 */
class SupportController extends Controller
{
    private function c(Request $request): Customer
    {
        $login = $request->user('customer');
        $id = $request->session()->get('ma_property');

        return $id && $id !== $login->id ? ($login->properties()->firstWhere('id', $id) ?? $login) : $login;
    }

    private function mine(Request $request, int $id): SupportTicket
    {
        $t = SupportTicket::findOrFail($id);
        abort_unless($t->customer_id === $this->c($request)->id, 404);

        return $t;
    }

    public function index(Request $request): View
    {
        $c = $this->c($request);

        return view('frontend.myaccount.support', ['c' => $c, 'tickets' => SupportTicket::where('customer_id', $c->id)->latest()->get()]);
    }

    public function create(Request $request): View
    {
        return view('frontend.myaccount.support-new', ['c' => $this->c($request)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $c = $this->c($request);
        $data = $request->validate(['topic' => ['required', Rule::in(array_keys(SupportTicket::TOPICS))], 'subject' => ['required', 'string', 'max:200'], 'body' => ['required', 'string', 'max:5000'],
            'attachments' => SupportTicketAttachment::RULES, 'attachments.*' => SupportTicketAttachment::FILE_RULES]);
        $t = SupportTicket::create(['customer_id' => $c->id, 'name' => $c->name, 'email' => $c->email ?? '', 'subject' => $data['subject'], 'topic' => $data['topic'],
            'channel' => 'My Account', 'status' => 'new', 'priority' => $data['topic'] === 'outage' ? 'high' : 'normal', 'last_activity_at' => now()]);
        $this->attach($request, $t->post('customer', $data['body']));
        AdminNotification::notify(User::whereIn('id', RangerAgent::where('active', true)->select('user_id'))->get(), 'ticket_new',
            'New ticket '.$t->number().': '.$t->subject, $c->name.' · '.(SupportTicket::TOPICS[$t->topic] ?? ''), route('ranger.tickets.show', $t));

        return redirect()->route('myaccount.support.show', $t->id)->with('status', 'Ticket '.$t->number().' is open. We\'ll reply here and email you.');
    }

    public function show(Request $request, int $ticket): View
    {
        $t = $this->mine($request, $ticket)->load(['messages.user', 'messages.attachments']);

        return view('frontend.myaccount.support-ticket', ['c' => $this->c($request), 't' => $t,
            'messages' => $t->messages->whereIn('author', ['customer', 'agent'])->values()]);
    }

    /** An attachment on one of your tickets (yours or ours). */
    public function file(Request $request, int $ticket, SupportTicketAttachment $attachment): StreamedResponse
    {
        $t = $this->mine($request, $ticket);
        abort_unless($attachment->support_ticket_id === $t->id && Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->name);
    }

    public function reply(Request $request, int $ticket): RedirectResponse
    {
        $t = $this->mine($request, $ticket);
        abort_if($t->status === 'closed', 403, 'This ticket is closed. Open a new one.');
        $data = $request->validate(['body' => ['required', 'string', 'max:5000'], 'attachments' => SupportTicketAttachment::RULES, 'attachments.*' => SupportTicketAttachment::FILE_RULES]);
        $this->attach($request, $t->post('customer', $data['body']));
        // Whoever has the ticket, and anyone who joined it, hears that the customer wrote back
        AdminNotification::notify(User::whereIn('id', array_filter([$t->assigned_to, ...$t->watchers()->pluck('users.id')]))->get(), 'ticket_reply',
            $t->name.' wrote back on '.$t->number(), mb_substr($data['body'], 0, 140), route('ranger.tickets.show', $t));

        return back()->with('status', 'Sent. We\'ll get back to you soon.');
    }

    /** Files sent with a message. */
    private function attach(Request $request, SupportTicketMessage $m): void
    {
        foreach ($request->file('attachments', []) as $file) {
            SupportTicketAttachment::store($m, $file, null, true);
        }
    }

    /** "Did we help?" once the ticket is solved: thumbs up or down, with an optional comment. */
    public function rate(Request $request, int $ticket): RedirectResponse
    {
        $t = $this->mine($request, $ticket);
        abort_unless($t->isDone(), 403, 'You can rate a ticket once it\'s solved.');
        $data = $request->validate(['rating' => ['required', 'in:good,bad'], 'comment' => ['nullable', 'string', 'max:1000']]);
        $t->update(['rating' => $data['rating'], 'rating_comment' => $data['comment'] ?? null, 'rated_at' => now()]);
        $t->post('system', 'The customer rated this '.($data['rating'] === 'good' ? '👍 good' : '👎 bad').'.');

        return back()->with('status', 'Thanks for letting us know.');
    }
}
