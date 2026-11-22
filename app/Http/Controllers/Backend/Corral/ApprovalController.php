<?php

namespace App\Http\Controllers\Backend\Corral;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Customer;
use App\Models\LeadApproval;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Services\AccountActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Lead Approvals: on a Friends & Family account, a money or service change from someone who isn't a Lead becomes a
 * request; a Customer Service Lead (or Administrator) approves it, which makes the change, or denies it.
 */
class ApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['pending', 'approved', 'denied'], true) ? $request->query('status') : 'pending';
        $items = LeadApproval::with(['customer', 'requester', 'decider', 'ledgerEntry'])->where('status', $status)
            ->when(! $request->user()->isLead(), fn ($q) => $q->where('requested_by', $request->user()->id))   // your own requests
            ->latest('id')->paginate(10)->withQueryString();

        return view('backend.corral.approvals.index', ['items' => $items, 'status' => $status, 'isLead' => $request->user()->isLead(),
            'counts' => LeadApproval::when(! $request->user()->isLead(), fn ($q) => $q->where('requested_by', $request->user()->id))
                ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')]);
    }

    public function show(Request $request, LeadApproval $approval): View
    {
        abort_unless($request->user()->isLead() || $approval->requested_by === $request->user()->id, 404);

        return view('backend.corral.approvals.show', ['a' => $approval->load(['customer', 'requester', 'decider', 'ledgerEntry']), 'isLead' => $request->user()->isLead()]);
    }

    /** "Request Lead Approval" on an account action's form. */
    public function requestAction(Request $request, Customer $customer, string $action): RedirectResponse
    {
        abort_unless(AccountActions::exists($action) && in_array($action, $customer->lockedActionsFor($request->user()), true), 404);
        $input = $request->validate(AccountController::rules($customer, $action));
        $note = $request->validate(['approval_note' => ['nullable', 'string', 'max:500']])['approval_note'] ?? null;

        return $this->create($request, $customer, $action, $input, null, $note);
    }

    /** "Request Delete" on a pending credit or charge. */
    public function requestLedgerDelete(Request $request, LedgerEntry $entry): RedirectResponse
    {
        abort_if($entry->status !== 'pending' || $entry->customer->canDeleteLedger($request->user()), 404);

        return $this->create($request, $entry->customer, 'ledger-delete', null, $entry, null);
    }

    private function create(Request $request, Customer $c, string $action, ?array $input, ?LedgerEntry $entry, ?string $note): RedirectResponse
    {
        $open = LeadApproval::pending()->where('customer_id', $c->id)->where('action', $action)->where('ledger_entry_id', $entry?->id)->where('input', json_encode($input))->first();
        $a = $open ?? LeadApproval::create(['customer_id' => $c->id, 'action' => $action, 'input' => $input, 'ledger_entry_id' => $entry?->id, 'note' => $note, 'requested_by' => $request->user()->id]);
        if (! $open) {
            $c->notes()->create(['user_id' => $request->user()->id, 'author' => $request->user()->name, 'category' => 'Account', 'action' => 'Lead Approval', 'priority' => 'Normal',
                'body' => 'Asked a Lead to approve: '.$a->label().($a->details() ? ' ('.$a->details().')' : '').'. Request #'.$a->id.'.']);
            $leads = User::where('active', true)->with('role')->get()->filter(fn ($u) => $u->isLead() && $u->id !== $request->user()->id);
            AdminNotification::notify($leads, 'approval', $request->user()->name.' asked for approval: '.$a->label(), $c->name.' · '.$c->account.($a->details() ? ' · '.$a->details() : ''), route('corral.approvals.show', $a));
        }

        return redirect(route('corral.customers.show', $c).'#actions')->with('status', 'Sent to a Lead for approval (request #'.$a->id.').');
    }

    /** Approve: the change is made now, under the Lead's name. */
    public function approve(Request $request, LeadApproval $approval): RedirectResponse
    {
        abort_unless($request->user()->isLead() && $approval->status === 'pending', 403);
        $note = $request->validate(['decision_note' => ['nullable', 'string', 'max:500']])['decision_note'] ?? null;
        $c = $approval->customer;
        $result = DB::transaction(function () use ($approval, $c, $request) {
            if ($approval->action === 'ledger-delete') {
                $approval->ledgerEntry?->update(['status' => 'deleted']);

                return 'Pending '.($approval->ledgerEntry?->kind ?? 'item').' deleted';
            }

            return (new AccountActions($c, $request->user()))->run($approval->action, $approval->input ?? [])[0];
        });
        $approval->update(['status' => 'approved', 'decided_by' => $request->user()->id, 'decided_at' => now(), 'decision_note' => $note, 'result' => mb_substr((string) $result, 0, 500)]);
        $this->tellRequester($approval, 'approved');

        return redirect()->route('corral.approvals.show', $approval)->with('status', 'Approved: '.$result);
    }

    public function deny(Request $request, LeadApproval $approval): RedirectResponse
    {
        abort_unless($request->user()->isLead() && $approval->status === 'pending', 403);
        $note = $request->validate(['decision_note' => ['required', 'string', 'max:500']], ['decision_note.required' => 'Say why, so they know what to tell the customer.'])['decision_note'];
        $approval->update(['status' => 'denied', 'decided_by' => $request->user()->id, 'decided_at' => now(), 'decision_note' => $note]);
        $approval->customer->notes()->create(['user_id' => $request->user()->id, 'author' => $request->user()->name, 'category' => 'Account', 'action' => 'Lead Approval', 'priority' => 'Normal',
            'body' => 'Denied request #'.$approval->id.' ('.$approval->label().'): '.$note]);
        $this->tellRequester($approval, 'denied');

        return redirect()->route('corral.approvals')->with('status', 'Request #'.$approval->id.' denied.');
    }

    private function tellRequester(LeadApproval $a, string $how): void
    {
        if ($a->requester && $a->requester->id !== $a->decided_by) {
            AdminNotification::notify($a->requester, 'approval', 'Your request was '.$how.': '.$a->label(), $a->customer->name.' · '.$a->customer->account.($a->decision_note ? ' · '.$a->decision_note : ''), route('corral.approvals.show', $a));
        }
    }
}
