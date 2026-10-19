<?php

namespace App\Http\Controllers\Backend\Corral;

use App\Http\Controllers\Controller;
use App\Models\AiAgent;
use App\Models\AiConversation;
use App\Models\ContactLog;
use App\Models\ContactMessage;
use App\Models\Customer;
use App\Models\Phonecall;
use App\Support\History;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * New Contact (from a call, a text or the account page): log a call, email, text or chat that
 * happened, with how it ended. And the resolution (Resolved, Incomplete…) of any call, email or text.
 */
class ContactController extends Controller
{
    public function create(Request $request): View
    {
        $c = $request->query('account') ? Customer::where('account', $request->query('account'))->first() : null;
        $channel = array_key_exists((string) $request->query('channel'), config('corral.contact_channels')) ? $request->query('channel') : 'call';

        return view('backend.corral.contact-form', ['c' => $c, 'channel' => $channel,
            'issues' => Phonecall::whereNotNull('disposition')->distinct()->orderBy('disposition')->pluck('disposition')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account' => ['required', 'string', 'exists:customers,account'],
            'channel' => ['required', Rule::in(array_keys(config('corral.contact_channels')))],
            'direction' => ['required', 'in:in,out'],
            'issue' => ['nullable', 'string', 'max:60'],
            'resolution' => ['required', Rule::in(array_keys(config('corral.resolutions')))],
            'minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'body' => ['required', 'string', 'max:5000'],
        ]);
        $c = Customer::where('account', $data['account'])->firstOrFail();
        $u = $request->user();
        $resolved = ['resolution' => $data['resolution'], 'resolved_by' => $u->id, 'resolved_at' => now()];

        $record = match ($data['channel']) {
            'call' => Phonecall::create(['customer_id' => $c->id, 'user_id' => $u->id, 'phone' => $c->phone, 'direction' => $data['direction'] === 'in' ? 'inbound' : 'outbound',
                'started_at' => now()->subMinutes((int) ($data['minutes'] ?? 0)), 'duration_sec' => (int) ($data['minutes'] ?? 0) * 60, 'disposition' => $data['issue'] ?? null,
                'transcript' => $data['body']] + $resolved),
            'chat' => AiConversation::create(['ai_agent_id' => AiAgent::where('activity', 'chat')->value('id') ?? AiAgent::value('id'), 'customer_id' => $c->id, 'channel' => 'chat',
                'topic' => $data['issue'] ?? 'Chat', 'status' => 'closed', 'user_id' => $u->id, 'transcript' => ($data['direction'] === 'in' ? 'Customer: ' : 'Agent: ').str_replace("\n", ' ', $data['body']),
                'started_at' => now(), 'last_message_at' => now()] + $resolved),
            default => ContactLog::create(['customer_id' => $c->id, 'channel' => $data['channel'] === 'email' ? 'Email' : 'SMS', 'template' => $data['issue'] ?? 'Logged by hand',
                'body' => $data['body'], 'status' => 'logged', 'direction' => $data['direction'], 'phone' => $data['channel'] === 'sms' ? $c->phone : null,
                'sent_at' => now(), 'user_id' => $u->id] + $resolved),
        };
        $label = config('corral.contact_channels')[$data['channel']];
        $c->notes()->create(['user_id' => $u->id, 'author' => $u->name, 'category' => 'Contact', 'action' => $label, 'priority' => 'Low',
            'body' => $label.' ('.($data['direction'] === 'in' ? 'from' : 'to').' the customer, '.$data['resolution'].')'.(! empty($data['issue']) ? ' — '.$data['issue'] : '').': '.$data['body']]);
        History::record(['customer_id' => $c->id, 'model' => class_basename($record), 'group' => 'Contact', 'record_id' => $record->getKey(),
            'action' => 'created', 'summary' => $label.' logged: '.$data['resolution']]);

        $to = match ($data['channel']) {
            'call' => route('corral.calls.transcript', $record),
            'sms' => route('corral.sms.show', $record),
            default => route('corral.customers.contact-log', $c),
        };

        return redirect($to)->with('status', $label.' logged as '.$data['resolution'].'.');
    }

    /** Resolved, Incomplete… for a call ("call"), an email or text ("contact"), a website message ("message") or a chat ("chat", from Deputy). */
    public function resolution(Request $request, string $type, int $id): RedirectResponse
    {
        $data = $request->validate(['resolution' => ['required', Rule::in(array_keys(config('corral.resolutions')))]]);
        $record = match ($type) {
            'call' => Phonecall::findOrFail($id),
            'contact' => ContactLog::findOrFail($id),
            'message' => ContactMessage::findOrFail($id),
            'chat' => AiConversation::findOrFail($id),
            default => abort(404),
        };
        $record->update(['resolution' => $data['resolution'], 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);

        return back()->with('status', 'Marked '.$data['resolution'].'.');
    }
}
