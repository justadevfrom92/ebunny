<?php

namespace App\Http\Controllers\Backend\Ranger;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\RangerAgent;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ranger: the support tickets customers open from My Account. The queue, each ticket's conversation
 * (replies the customer sees, internal notes they don't), and for managers Take Over and Join In.
 */
class TicketController extends Controller
{
    public function dashboard(Request $request): View
    {
        $since = now()->subDays(30);
        $teams = collect(RangerAgent::TEAMS)->map(fn ($label, $team) => SupportTicket::approval($this->teamTickets($team)->where('rated_at', '>=', $since)) + ['label' => $label]);

        return view('backend.ranger.dashboard', [
            'counts' => [
                'new' => SupportTicket::where('status', 'new')->count(),
                'unassigned' => SupportTicket::active()->whereNull('assigned_to')->count(),
                'open' => SupportTicket::active()->count(),
                'solvedToday' => SupportTicket::whereDate('solved_at', today())->count(),
            ],
            'overall' => SupportTicket::approval(SupportTicket::where('rated_at', '>=', $since)),
            'teams' => $teams,
            'recentRatings' => SupportTicket::whereNotNull('rated_at')->with('assignee')->latest('rated_at')->limit(8)->get(),
            'managerRequests' => SupportTicket::active()->whereNotNull('manager_requested_at')->with(['customer', 'managerRequester'])->oldest('manager_requested_at')->get(),
            'manager' => RangerAgent::isManager($request->user()),
        ]);
    }

    public function index(Request $request): View
    {
        $f = $request->validate([
            'view' => ['nullable', 'in:active,mine,unassigned,manager,watching,done,all'],
            'customer' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(array_keys(SupportTicket::STATUSES))],
            'team' => ['nullable', Rule::in(array_keys(RangerAgent::TEAMS))],
            'owner' => ['nullable', 'integer'],
        ]);
        $view = $f['view'] ?? 'active';
        $me = $request->user();

        $tickets = SupportTicket::with(['customer', 'assignee'])
            ->when($view === 'active', fn ($x) => $x->active())
            ->when($view === 'mine', fn ($x) => $x->active()->where('assigned_to', $me->id))
            ->when($view === 'unassigned', fn ($x) => $x->active()->whereNull('assigned_to'))
            ->when($view === 'manager', fn ($x) => $x->active()->whereNotNull('manager_requested_at'))
            ->when($f['customer'] ?? null, fn ($x, $c) => $x->where('customer_id', $c))
            ->when($view === 'watching', fn ($x) => $x->whereHas('watchers', fn ($w) => $w->where('users.id', $me->id)))
            ->when($view === 'done', fn ($x) => $x->whereIn('status', ['solved', 'closed']))
            ->when($f['status'] ?? null, fn ($x, $s) => $x->where('status', $s))
            ->when($f['owner'] ?? null, fn ($x, $o) => $x->where('assigned_to', $o))
            ->when($f['team'] ?? null, fn ($x, $t) => $x->whereIn('assigned_to', RangerAgent::where('team', $t)->select('user_id')))
            ->orderByRaw(in_array($view, ['done', 'all'], true) ? 'coalesce(last_activity_at, created_at) desc' : $this->urgency())
            ->paginate(25)->withQueryString();

        return view('backend.ranger.tickets', ['tickets' => $tickets, 'f' => $f, 'view' => $view, 'owner' => isset($f['owner']) ? User::find($f['owner']) : null,
            'customer' => isset($f['customer']) ? Customer::find($f['customer']) : null, 'manager' => RangerAgent::isManager($me), 'counts' => [
                'active' => SupportTicket::active()->count(), 'mine' => SupportTicket::active()->where('assigned_to', $me->id)->count(),
                'unassigned' => SupportTicket::active()->whereNull('assigned_to')->count(),
                'manager' => SupportTicket::active()->whereNotNull('manager_requested_at')->count(),
                'watching' => SupportTicket::whereHas('watchers', fn ($w) => $w->where('users.id', $me->id))->count(),
                'done' => SupportTicket::whereIn('status', ['solved', 'closed'])->count(), 'all' => SupportTicket::count(),
            ]]);
    }

    public function show(Request $request, SupportTicket $ticket): View
    {
        $ticket->load(['customer.plan', 'assignee', 'watchers', 'messages.user', 'messages.attachments', 'notes.user', 'attachments.user', 'managerRequester']);

        return view('backend.ranger.ticket', ['t' => $ticket, 'manager' => RangerAgent::isManager($request->user()),
            'agents' => RangerAgent::with('user')->where('active', true)->get()->sortBy('user.name')
                ->when(RangerAgent::companyOf($request->user()), fn ($c, $co) => $c->filter(fn ($a) => $a->user->company === $co))->values(),
            'agentOf' => RangerAgent::whereIn('user_id', array_filter([$ticket->assigned_to, ...$ticket->watchers->pluck('id')]))->get()->keyBy('user_id'),
            'history' => $ticket->customer_id ? SupportTicket::where('customer_id', $ticket->customer_id)->count() : 0]);
    }

    /** A reply to the customer (in My Account or by email) or a phone call logged with its direction and length; optionally a new status. */
    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000'],
            'via' => ['nullable', Rule::in(array_keys(SupportTicketMessage::VIA))], 'direction' => ['nullable', 'in:in,out'], 'minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'status' => ['nullable', Rule::in(['open', 'waiting', 'solved'])]]);
        $this->mayWork($request, $ticket);
        $me = $request->user();
        $via = $data['via'] ?? 'portal';
        $ticket->post('agent', $data['body'], $me, ['via' => $via] + match ($via) {
            'phone' => ['direction' => $data['direction'] ?? 'out', 'duration' => (int) ($data['minutes'] ?? 0) * 60, 'contact' => $ticket->customer?->phone],
            'email' => ['contact' => $ticket->email],
            default => [],
        });
        if (! $ticket->assigned_to) {
            $ticket->update(['assigned_to' => $me->id]);   // whoever answers first owns it
        }
        if ($status = $data['status'] ?? null) {
            $ticket->update(['status' => $status, 'solved_at' => $status === 'solved' ? now() : null]);
        }

        return back()->with('status', ['portal' => 'Reply sent', 'email' => 'Email sent', 'phone' => 'Phone call logged'][$via].(isset($status) ? ' · '.SupportTicket::STATUSES[$status][0] : ''));
    }

    /** A note on the ticket: an account Note (category, action, priority), staff only. */
    public function addNote(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $categories = config('corral.note_categories');
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'category' => ['required', Rule::in(array_keys($categories))],
            'action' => ['required', 'string', Rule::in($categories[$request->input('category')] ?? [])],
            'priority' => ['required', Rule::in(config('corral.priorities'))],
        ]);
        $this->mayWork($request, $ticket);
        $ticket->addNote($data['body'], $request->user(), $data['priority'], $data['category'], $data['action']);

        return redirect(route('ranger.tickets.show', $ticket).'#notes')->with('status', 'Note added (it\'s on the customer\'s account notes too)');
    }

    /** One attachment: who sent it, when, on which message, with a preview for images, PDFs and text. */
    public function attachment(SupportTicket $ticket, SupportTicketAttachment $attachment): View
    {
        abort_unless($attachment->support_ticket_id === $ticket->id, 404);
        $attachment->load(['message.user', 'user']);
        $text = $attachment->isText() ? Str::limit((string) Storage::disk('local')->get($attachment->path), 20000) : null;

        return view('backend.ranger.attachment', ['t' => $ticket, 'a' => $attachment, 'text' => $text]);
    }

    /** The file itself: inline (for the preview) or as a download. */
    public function attachmentFile(Request $request, SupportTicket $ticket, SupportTicketAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->support_ticket_id === $ticket->id && Storage::disk('local')->exists($attachment->path), 404);

        return $request->boolean('inline') && ($attachment->isImage() || $attachment->isPdf())
            ? Storage::disk('local')->response($attachment->path, $attachment->name, ['Content-Type' => $attachment->mime])
            : Storage::disk('local')->download($attachment->path, $attachment->name);
    }

    /** Status, priority or assignee. */
    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(SupportTicket::STATUSES))],
            'priority' => ['nullable', Rule::in(array_keys(SupportTicket::PRIORITIES))],
            'assigned_to' => ['nullable', Rule::exists('ranger_agents', 'user_id')->where('active', true)],
        ]);
        $me = $request->user();
        $changes = [];
        if ($s = $data['status'] ?? null) {
            $changes += ['status' => $s, 'solved_at' => in_array($s, ['solved', 'closed'], true) ? ($ticket->solved_at ?? now()) : null];
        }
        if ($p = $data['priority'] ?? null) {
            $changes['priority'] = $p;
        }
        if (array_key_exists('assigned_to', $data) && $data['assigned_to'] && (int) $data['assigned_to'] !== $ticket->assigned_to) {
            // Agents can pick a ticket up for themselves; handing one to someone else is for managers
            abort_unless((int) $data['assigned_to'] === $me->id || RangerAgent::isManager($me), 403, 'Only managers can assign tickets to someone else.');
            if ($co = RangerAgent::companyOf($me)) {
                abort_unless(User::whereKey($data['assigned_to'])->value('company') === $co, 403, 'You can only assign tickets to people from '.$co.'.');
            }
            $changes['assigned_to'] = (int) $data['assigned_to'];
            $ticket->post('system', $me->name.' assigned this to '.User::find($data['assigned_to'])->name.'.', $me);
        }
        if ($changes) {
            if (isset($changes['status']) && $changes['status'] !== $ticket->status) {
                $ticket->post('system', $me->name.' set the status to '.SupportTicket::STATUSES[$changes['status']][0].'.', $me);
            }
            $ticket->update($changes);
        }

        return back()->with('status', 'Ticket updated');
    }

    /** Managers: take the ticket over (it becomes yours; the agent who had it stays on as a watcher). */
    public function takeOver(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $me = $request->user();
        abort_unless(RangerAgent::isManager($me), 403);
        if ($ticket->assigned_to && $ticket->assigned_to !== $me->id) {
            $ticket->watchers()->syncWithoutDetaching([$ticket->assigned_to => ['joined_at' => now()]]);
        }
        $ticket->watchers()->detach($me->id);
        $from = $ticket->assignee?->name;
        $ticket->update(['assigned_to' => $me->id, 'status' => $ticket->status === 'new' ? 'open' : $ticket->status, 'manager_requested_at' => null]);
        $ticket->post('system', $me->name.' took over this ticket'.($from && $from !== $me->name ? ' from '.$from : '').'.', $me);

        return back()->with('status', 'You took over '.$ticket->number().'.');
    }

    /** Managers: join in (follow it and reply alongside the agent who owns it). */
    public function join(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $me = $request->user();
        abort_unless(RangerAgent::isManager($me), 403);
        if ($ticket->assigned_to !== $me->id) {
            $ticket->watchers()->syncWithoutDetaching([$me->id => ['joined_at' => now()]]);
            $ticket->post('system', $me->name.' joined in.', $me);
        }
        $ticket->update(['manager_requested_at' => null]);   // a manager is on it

        return back()->with('status', 'You joined '.$ticket->number().'. You can reply and add notes alongside '.($ticket->assignee?->name ?? 'the team').'.');
    }

    /** Agents: ask a manager to help (they see it on the dashboard and under Manager Requests). */
    public function askManager(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->mayWork($request, $ticket);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $ticket->update(['manager_requested_at' => now(), 'manager_requested_by' => $request->user()->id, 'manager_request_note' => $data['reason'] ?? null]);
        if (! empty($data['reason'])) {
            $ticket->addNote($data['reason'], $request->user(), 'Medium', 'Ticket', 'Manager Request');   // the why is a note, not a line in the conversation
        }

        return back()->with('status', 'A manager has been asked to help on '.$ticket->number().'.');
    }

    public function cancelManager(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->mayWork($request, $ticket);
        $ticket->update(['manager_requested_at' => null]);

        return back()->with('status', 'Manager request cancelled.');
    }

    public function leave(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $ticket->watchers()->detach($request->user()->id);
        $ticket->post('system', $request->user()->name.' left the ticket.', $request->user());

        return back()->with('status', 'You left '.$ticket->number().'.');
    }

    /** The owner, anyone who joined, a manager, or anyone picking up an unassigned ticket. */
    private function mayWork(Request $request, SupportTicket $ticket): void
    {
        $me = $request->user();
        abort_unless(! $ticket->assigned_to || $ticket->assigned_to === $me->id || RangerAgent::isManager($me)
            || $ticket->watchers()->where('users.id', $me->id)->exists(), 403, 'This ticket belongs to '.$ticket->assignee?->name.'. Ask a manager to join you in.');
    }

    /** Urgent first, then high…, then the longest waiting. */
    private function urgency(): string
    {
        return "case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end, case status when 'new' then 0 when 'open' then 1 else 2 end, created_at asc";
    }

    /** Tickets worked by one team (by who they're assigned to). */
    private function teamTickets(string $team): Builder
    {
        return SupportTicket::whereIn('assigned_to', RangerAgent::where('team', $team)->select('user_id'));
    }
}
