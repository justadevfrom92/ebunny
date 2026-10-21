<?php

namespace App\Http\Controllers\Backend\Ranger;

use App\Http\Controllers\Controller;
use App\Models\RangerAgent;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Ranger → Team: everyone who works tickets (in-office and 3rd-party), with their open tickets and
 * approval %, and adding someone — a person already in the admin, or a new person (with a sign-in).
 */
class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate(['team' => ['nullable', Rule::in(array_keys(RangerAgent::TEAMS))], 'days' => ['nullable', 'in:30,90,365']]);
        $since = now()->subDays((int) ($f['days'] ?? 90));
        $agents = RangerAgent::with(['user', 'adder'])->when($f['team'] ?? null, fn ($q, $t) => $q->where('team', $t))->get()->sortBy([['active', 'desc'], ['level', 'desc'], ['user.name', 'asc']])->values();
        $stats = SupportTicket::whereIn('assigned_to', $agents->pluck('user_id'))->selectRaw("assigned_to,
            sum(case when status in ('new','open','waiting') then 1 else 0 end) as open,
            sum(case when solved_at >= ? then 1 else 0 end) as solved,
            sum(case when rated_at >= ? then 1 else 0 end) as rated,
            sum(case when rated_at >= ? and rating = 'good' then 1 else 0 end) as good", [$since, $since, $since])->groupBy('assigned_to')->get()->keyBy('assigned_to');

        return view('backend.ranger.team', ['agents' => $agents, 'stats' => $stats, 'f' => $f, 'manager' => RangerAgent::isManager($request->user())]);
    }

    public function create(Request $request): View
    {
        return view('backend.ranger.team-form', [
            'people' => User::where('active', true)->whereNotIn('id', RangerAgent::select('user_id'))->with('role')->orderBy('name')->get(),
            'manager' => RangerAgent::isManager($request->user()),
        ]);
    }

    /** Add someone already in the admin (they get Ranger), or a new person with a temporary password. */
    public function store(Request $request): RedirectResponse
    {
        $manager = RangerAgent::isManager($request->user());
        $data = $request->validate([
            'who' => ['required', 'in:existing,new'],
            'user_id' => ['required_if:who,existing', 'nullable', 'exists:users,id', Rule::unique('ranger_agents', 'user_id')],
            'name' => ['required_if:who,new', 'nullable', 'string', 'max:150'],
            'email' => ['required_if:who,new', 'nullable', 'email', 'max:200', 'unique:users,email'],
            'password' => ['required_if:who,new', 'nullable', Password::min(12)],
            'title' => ['nullable', 'string', 'max:100'],
            'team' => ['required', Rule::in(array_keys(RangerAgent::TEAMS))],
            'company' => [Rule::requiredIf(fn () => $request->input('who') === 'new' && $request->input('team') === 'third_party'), 'nullable', 'string', 'max:120'],
            'level' => ['required', Rule::in(array_keys(RangerAgent::LEVELS))],
        ], ['company.required' => 'Give the 3rd-party company they work for.']);
        // Only managers can add a manager
        abort_if($data['level'] === 'manager' && ! $manager, 403, 'Only a manager can add another manager.');

        $user = DB::transaction(function () use ($data, $request) {
            if ($data['who'] === 'new') {
                $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'active' => true,
                    'role_id' => Role::where('name', 'Customer Service')->value('id'), 'title' => $data['title'] ?? ($data['level'] === 'manager' ? 'Support Manager' : 'Support Agent'),
                    'company' => $data['team'] === 'third_party' ? $data['company'] : null, 'department' => $data['team'] === 'office' ? 'Customer Service' : null,
                    'manager_id' => $request->user()->id, 'hired_on' => today()]);
            } else {
                $user = User::findOrFail($data['user_id']);
            }
            if (! $user->hasPerm('ranger')) {
                $user->update(['extra_perms' => array_values(array_unique([...($user->extra_perms ?? []), 'ranger'])),
                    'denied_perms' => array_values(array_diff($user->denied_perms ?? [], ['ranger']))]);
            }
            RangerAgent::create(['user_id' => $user->id, 'team' => $data['team'], 'level' => $data['level'], 'added_by' => $request->user()->id]);

            return $user;
        });

        return redirect()->route('ranger.team')->with('status', $user->name.' can now work tickets'.($data['who'] === 'new' ? '. Share the temporary password with them securely.' : '.'));
    }

    /** One team member: who they are, their numbers, open tickets, approval by week and what customers said. */
    public function show(Request $request, RangerAgent $agent): View
    {
        $agent->load(['user.role', 'adder']);
        $mine = fn () => SupportTicket::where('assigned_to', $agent->user_id);
        $approval = collect([30, 90, 365])->mapWithKeys(fn ($d) => [$d => SupportTicket::approval($mine()->where('rated_at', '>=', now()->subDays($d)))]);
        $firstReply = $mine()->whereNotNull('first_response_at')->where('created_at', '>=', now()->subDays(90))->get(['created_at', 'first_response_at'])
            ->avg(fn ($t) => $t->created_at->diffInMinutes($t->first_response_at));
        $weeks = collect(range(11, 0))->map(function ($w) use ($mine) {
            $from = now()->startOfWeek()->subWeeks($w);

            return ['week' => $from] + SupportTicket::approval($mine()->whereBetween('rated_at', [$from, $from->copy()->endOfWeek()]));
        });

        return view('backend.ranger.team-show', ['agent' => $agent, 'approval' => $approval, 'firstReply' => $firstReply, 'weeks' => $weeks,
            'open' => $mine()->active()->with('customer')->orderBy('created_at')->get(),
            'solved' => $mine()->where('solved_at', '>=', now()->subDays(90))->count(),
            'ratings' => $mine()->whereNotNull('rated_at')->latest('rated_at')->limit(30)->get(),
            'manager' => RangerAgent::isManager($request->user())]);
    }

    /** Managers: change someone's team or level, or take them off tickets. */
    public function update(Request $request, RangerAgent $agent): RedirectResponse
    {
        abort_unless(RangerAgent::isManager($request->user()), 403);
        $data = $request->validate(['team' => ['nullable', Rule::in(array_keys(RangerAgent::TEAMS))], 'level' => ['nullable', Rule::in(array_keys(RangerAgent::LEVELS))], 'active' => ['nullable', 'boolean']]);
        $agent->update(array_filter($data, fn ($v) => $v !== null));
        if (array_key_exists('active', $data) && ! $agent->active) {
            // Their open tickets go back to the queue
            SupportTicket::active()->where('assigned_to', $agent->user_id)->update(['assigned_to' => null]);
        }

        return back()->with('status', $agent->user->name.' updated');
    }

    /** Approval %: in-office vs 3rd party, each person, and what customers said. */
    public function satisfaction(Request $request): View
    {
        $f = $request->validate(['days' => ['nullable', 'in:30,90,365']]);
        $since = now()->subDays((int) ($f['days'] ?? 90));
        $rated = fn () => SupportTicket::where('rated_at', '>=', $since);
        $teams = collect(RangerAgent::TEAMS)->map(fn ($label, $team) => SupportTicket::approval($rated()->whereIn('assigned_to', RangerAgent::where('team', $team)->select('user_id'))) + ['label' => $label]);
        $people = RangerAgent::with('user')->get()->map(fn ($a) => ['agent' => $a] + SupportTicket::approval($rated()->where('assigned_to', $a->user_id)))
            ->filter(fn ($p) => $p['rated'] > 0)->sortByDesc(fn ($p) => [$p['pct'], $p['rated']])->values();
        // Approval by week, per team, for the trend
        $weeks = collect(range(11, 0))->map(function ($w) use ($teams) {
            $from = now()->startOfWeek()->subWeeks($w);
            $row = ['week' => $from];
            foreach (array_keys($teams->all()) as $team) {
                $row[$team] = SupportTicket::approval(SupportTicket::whereBetween('rated_at', [$from, $from->copy()->endOfWeek()])->whereIn('assigned_to', RangerAgent::where('team', $team)->select('user_id')));
            }

            return $row;
        });

        return view('backend.ranger.satisfaction', ['f' => $f, 'overall' => SupportTicket::approval($rated()), 'teams' => $teams, 'people' => $people, 'weeks' => $weeks,
            'comments' => $rated()->whereNotNull('rating_comment')->with('assignee')->latest('rated_at')->paginate(10)->withQueryString()]);
    }
}
