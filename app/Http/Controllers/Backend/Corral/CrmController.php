<?php

namespace App\Http\Controllers\Backend\Corral;

use App\Http\Controllers\Controller;
use App\Models\CrmActivity;
use App\Models\CrmLead;
use App\Models\CrmPartner;
use App\Models\Customer;
use App\Models\Market;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Corral → CRM: the sales side of customer relationships. Leads (homes and
 * businesses that might sign up) move through a pipeline; every call, email,
 * meeting and follow-up is logged on the lead; brokers and referral partners
 * send leads and earn commission on the ones we win.
 */
class CrmController extends Controller
{
    /** People who can own leads: anyone with Corral. */
    private function owners(): Collection
    {
        return User::where('active', true)->orderBy('name')->get()->filter(fn ($u) => $u->hasPerm('corral'))->values();
    }

    // ---------- Pipeline ----------

    public function pipeline(Request $request): View
    {
        $mine = $request->query('owner') === 'me';
        $open = CrmLead::with(['owner', 'partner'])->open()->when($mine, fn ($q) => $q->where('owner_id', $request->user()->id))
            ->orderBy('next_follow_up_on')->get();
        $closed = CrmLead::whereIn('stage', ['won', 'lost'])->where('closed_at', '>=', now()->subDays(90))->get();
        $wonMonth = CrmLead::where('stage', 'won')->where('closed_at', '>=', now()->startOfMonth())->get();

        return view('backend.corral.crm.pipeline', [
            'mine' => $mine,
            'stages' => collect(CrmLead::OPEN)->mapWithKeys(fn ($s) => [$s => $open->where('stage', $s)->values()]),
            'stats' => [
                'open' => $open->count(),
                'value' => $open->sum('est_value'),
                'weighted' => $open->sum(fn ($l) => $l->weighted()),
                'wonMonth' => $wonMonth->count(), 'wonValue' => $wonMonth->sum('est_value'),
                'winRate' => $closed->count() ? round(100 * $closed->where('stage', 'won')->count() / $closed->count()) : 0,
                'overdue' => $open->filter->followUpOverdue()->count(),
            ],
            'bySource' => CrmLead::where('created_at', '>=', now()->subDays(90))->get()->groupBy('source')
                ->map(fn ($g, $s) => ['label' => $s, 'value' => $g->count(), 'tip' => $s.' ('.$g->where('stage', 'won')->count().' won)'])->sortByDesc('value')->values(),
            'recentWins' => CrmLead::with('owner')->where('stage', 'won')->latest('closed_at')->limit(6)->get(),
        ]);
    }

    // ---------- Leads ----------

    public function leads(Request $request): View
    {
        $f = $request->validate([
            'stage' => ['nullable', Rule::in([...array_keys(CrmLead::STAGES), 'open'])],
            'kind' => ['nullable', Rule::in(CrmLead::KINDS)],
            'source' => ['nullable', Rule::in(CrmLead::SOURCES)],
            'owner' => ['nullable', 'in:me'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $leads = CrmLead::with(['owner', 'partner'])
            ->when(($f['stage'] ?? null) === 'open', fn ($q) => $q->open())
            ->when(($f['stage'] ?? 'open') !== 'open', fn ($q) => $q->where('stage', $f['stage']))
            ->when($f['kind'] ?? null, fn ($q, $v) => $q->where('kind', $v))
            ->when($f['source'] ?? null, fn ($q, $v) => $q->where('source', $v))
            ->when($f['owner'] ?? null, fn ($q) => $q->where('owner_id', $request->user()->id))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', "%$v%")->orWhere('company', 'like', "%$v%")->orWhere('email', 'like', "%$v%")->orWhere('phone', 'like', "%$v%")))
            ->orderByRaw("case when stage in ('won','lost') then 1 else 0 end")->orderBy('next_follow_up_on')->latest('id')
            ->paginate(30)->withQueryString();

        return view('backend.corral.crm.leads', ['leads' => $leads, 'f' => $f,
            'counts' => CrmLead::selectRaw('stage, count(*) n')->groupBy('stage')->pluck('n', 'stage')]);
    }

    public function showLead(CrmLead $lead): View
    {
        $lead->load(['owner', 'partner', 'market', 'customer', 'activities' => fn ($q) => $q->with('user')->latest()->latest('id')]);

        return view('backend.corral.crm.lead', ['lead' => $lead,
            'open' => $lead->activities->filter->isOpenTask()->sortBy('due_on')->values(),
            'similar' => $lead->company ? Customer::where('name', 'like', '%'.$lead->company.'%')->limit(3)->get() : collect(),
        ]);
    }

    public function createLead(Request $request): View
    {
        return $this->leadForm(new CrmLead(['stage' => 'new', 'kind' => 'Small Business', 'source' => 'Website', 'owner_id' => $request->user()->id,
            'partner_id' => $request->query('partner'), 'next_follow_up_on' => today()->addDays(2)]));
    }

    public function editLead(CrmLead $lead): View
    {
        return $this->leadForm($lead);
    }

    private function leadForm(CrmLead $lead): View
    {
        return view('backend.corral.crm.lead-form', ['lead' => $lead, 'owners' => $this->owners(),
            'partners' => CrmPartner::where('status', '!=', 'Ended')->orderBy('name')->get(), 'markets' => Market::orderBy('name')->get()]);
    }

    public function saveLead(Request $request, ?CrmLead $lead = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'company' => ['nullable', 'required_unless:kind,Residential', 'string', 'max:150'],
            'title' => ['nullable', 'string', 'max:100'],
            'kind' => ['required', Rule::in(CrmLead::KINDS)],
            'email' => ['nullable', 'email', 'max:200'],
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:80'],
            'market_id' => ['nullable', 'exists:markets,id'],
            'source' => ['required', Rule::in(CrmLead::SOURCES)],
            'partner_id' => ['nullable', 'required_if:source,Broker,Partner', 'exists:crm_partners,id'],
            'annual_kwh' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'est_value' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'sites' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'current_provider' => ['nullable', 'string', 'max:80'],
            'contract_ends_on' => ['nullable', 'date'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'next_follow_up_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['company.required_unless' => 'Enter the business name.', 'partner_id.required_if' => 'Pick the broker or partner who sent this lead.']);
        $data['annual_kwh'] = (int) ($data['annual_kwh'] ?? 0);
        $data['sites'] = (int) ($data['sites'] ?? 1);
        // No yearly value given: estimate it from usage at about 14¢/kWh
        $data['est_value'] = (float) ($data['est_value'] ?? 0) ?: round($data['annual_kwh'] * 0.14, 2);
        if (! in_array($data['source'], ['Broker', 'Partner'], true)) {
            $data['partner_id'] = null;
        }

        if ($lead) {
            $lead->update($data);
        } else {
            $lead = CrmLead::create($data + ['stage' => 'new', 'stage_changed_at' => now()]);
            $lead->activities()->create(['user_id' => $request->user()->id, 'type' => 'note', 'subject' => 'Lead added from '.$lead->source]);
        }

        return redirect()->route('corral.crm.leads.show', $lead)->with('status', $lead->displayName().' saved');
    }

    /** Move a lead to another stage. Lost needs a reason; Won can link the new account. */
    public function moveStage(Request $request, CrmLead $lead): RedirectResponse
    {
        if (filled($request->input('lost_other'))) {
            $request->merge(['lost_reason' => $request->input('lost_other')]);
        }
        $data = $request->validate([
            'stage' => ['required', Rule::in(array_keys(CrmLead::STAGES))],
            'lost_reason' => ['nullable', 'required_if:stage,lost', 'string', 'max:200'],
            'account' => ['nullable', 'exists:customers,account'],
        ], ['lost_reason.required_if' => 'Say why it was lost.', 'account.exists' => 'No account with that number.']);
        if ($data['stage'] === $lead->stage) {
            return back();
        }
        $from = $lead->stageLabel();
        $closing = in_array($data['stage'], ['won', 'lost'], true);
        $lead->update([
            'stage' => $data['stage'], 'stage_changed_at' => now(), 'closed_at' => $closing ? now() : null,
            'lost_reason' => $data['stage'] === 'lost' ? $data['lost_reason'] : null,
            'customer_id' => $data['stage'] === 'won' && ! empty($data['account']) ? Customer::where('account', $data['account'])->value('id') : ($data['stage'] === 'won' ? $lead->customer_id : null),
            'next_follow_up_on' => $closing ? null : $lead->next_follow_up_on,
        ]);
        $lead->activities()->create(['user_id' => $request->user()->id, 'type' => 'stage', 'subject' => $from.' → '.$lead->stageLabel(), 'body' => $lead->lost_reason]);
        if ($closing) {
            $lead->activities()->whereNotNull('due_on')->whereNull('done_at')->update(['done_at' => now()]);
        }

        return back()->with('status', $lead->displayName().' moved to '.$lead->stageLabel());
    }

    /** Log a call, email, meeting or note, or set a follow-up. */
    public function logActivity(Request $request, CrmLead $lead): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(CrmActivity::TYPES))],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:2000'],
            'due_on' => ['nullable', 'required_if:type,task', 'date'],
        ], ['due_on.required_if' => 'Pick when to follow up.']);
        $lead->activities()->create($data + ['user_id' => $request->user()->id, 'done_at' => $data['type'] === 'task' ? null : now()]);
        if ($data['type'] === 'task') {
            $next = $lead->activities()->whereNotNull('due_on')->whereNull('done_at')->min('due_on');
            $lead->update(['next_follow_up_on' => $next]);
        } elseif ($lead->stage === 'new' && in_array($data['type'], ['call', 'email', 'meeting'], true)) {
            $lead->update(['stage' => 'contacted', 'stage_changed_at' => now()]);
            $lead->activities()->create(['user_id' => $request->user()->id, 'type' => 'stage', 'subject' => 'New → Contacted']);
        }

        return back()->with('status', CrmActivity::TYPES[$data['type']][0].' logged');
    }

    // ---------- Tasks ----------

    public function tasks(Request $request): View
    {
        $uid = $request->user()->id;
        $mine = fn ($q) => $q->where(fn ($w) => $w->where('user_id', $uid)->orWhereHas('lead', fn ($l) => $l->where('owner_id', $uid)));
        // Everyone's follow-ups when asked, or when you have none of your own
        $everyone = $request->query('who') === 'all'
            || (! $request->has('who') && ! $mine(CrmActivity::whereNotNull('due_on')->whereNull('done_at'))->exists());
        $base = CrmActivity::with(['lead', 'partner', 'user'])->whereNotNull('due_on')->when(! $everyone, $mine);
        $open = (clone $base)->whereNull('done_at')->orderBy('due_on')->get();

        return view('backend.corral.crm.tasks', ['everyone' => $everyone,
            'overdue' => $open->filter(fn ($t) => $t->due_on->lt(today()))->values(),
            'today' => $open->filter(fn ($t) => $t->due_on->isToday())->values(),
            'upcoming' => $open->filter(fn ($t) => $t->due_on->gt(today()))->values(),
            'done' => (clone $base)->whereNotNull('done_at')->where('done_at', '>=', now()->subDays(14))->latest('done_at')->limit(20)->get(),
        ]);
    }

    public function completeTask(Request $request, CrmActivity $activity): RedirectResponse
    {
        abort_unless($activity->due_on !== null, 404);
        $activity->update(['done_at' => $activity->done_at ? null : now()]);
        if ($lead = $activity->lead) {
            $lead->update(['next_follow_up_on' => $lead->activities()->whereNotNull('due_on')->whereNull('done_at')->min('due_on')]);
        }

        return back()->with('status', $activity->done_at ? 'Follow-up done' : 'Follow-up reopened');
    }

    // ---------- Partners & brokers ----------

    public function partners(): View
    {
        $partners = CrmPartner::with('owner')->withCount(['leads', 'leads as won_count' => fn ($q) => $q->where('stage', 'won'), 'leads as open_count' => fn ($q) => $q->open()])
            ->orderByRaw("case status when 'Active' then 0 when 'Paused' then 1 else 2 end")->orderBy('name')->get();
        $earned = CrmLead::with('partner')->where('stage', 'won')->whereNotNull('partner_id')->get()->groupBy('partner_id')
            ->map(fn ($g) => $g->sum(fn ($l) => $l->partner->commissionFor($l)));

        return view('backend.corral.crm.partners', ['partners' => $partners, 'earned' => $earned]);
    }

    public function showPartner(CrmPartner $partner): View
    {
        $partner->load(['owner', 'leads' => fn ($q) => $q->with('owner')->latest(), 'activities' => fn ($q) => $q->with('user')->latest()]);
        $won = $partner->leads->where('stage', 'won');

        return view('backend.corral.crm.partner', ['p' => $partner, 'won' => $won,
            'earned' => $won->sum(fn ($l) => $partner->commissionFor($l)),
            'pipeline' => $partner->leads->filter->isOpen()->sum('est_value')]);
    }

    public function createPartner(): View
    {
        return view('backend.corral.crm.partner-form', ['p' => new CrmPartner(['kind' => 'Broker', 'status' => 'Active', 'commission_basis' => 'kwh', 'commission_rate' => 0.004]), 'owners' => $this->owners()]);
    }

    public function editPartner(CrmPartner $partner): View
    {
        return view('backend.corral.crm.partner-form', ['p' => $partner, 'owners' => $this->owners()]);
    }

    public function savePartner(Request $request, ?CrmPartner $partner = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::in(CrmPartner::KINDS)],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:200'],
            'phone' => ['nullable', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:80'],
            'commission_basis' => ['required', Rule::in(['kwh', 'flat'])],
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'status' => ['required', Rule::in(CrmPartner::STATUSES)],
            'owner_id' => ['nullable', 'exists:users,id'],
            'agreement_ends_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $partner ? $partner->update($data) : $partner = CrmPartner::create($data);

        return redirect()->route('corral.crm.partners.show', $partner)->with('status', $partner->name.' saved');
    }

    public function logPartnerActivity(Request $request, CrmPartner $partner): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(CrmActivity::TYPES))],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:2000'],
            'due_on' => ['nullable', 'required_if:type,task', 'date'],
        ]);
        $partner->activities()->create($data + ['user_id' => $request->user()->id, 'done_at' => $data['type'] === 'task' ? null : now()]);

        return back()->with('status', CrmActivity::TYPES[$data['type']][0].' logged');
    }
}
