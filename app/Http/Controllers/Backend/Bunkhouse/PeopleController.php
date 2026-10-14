<?php

namespace App\Http\Controllers\Backend\Bunkhouse;

use App\Http\Controllers\Controller;
use App\Models\ErmCase;
use App\Models\ErmCheckin;
use App\Models\ErmChecklistItem;
use App\Models\ErmCourse;
use App\Models\ErmEnrollment;
use App\Models\ErmKudo;
use App\Models\ErmReview;
use App\Models\ErmTimeOff;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Bunkhouse: employee relationship management. The people in Sheriff → Users
 * (employees and 3rd-party company users) and how they're doing: reviews,
 * one-on-ones, training, kudos, time off, HR cases, onboarding and offboarding.
 * Access itself stays in Sheriff; nothing here links there.
 */
class PeopleController extends Controller
{
    private function people(): Collection
    {
        return User::where('active', true)->orderBy('name')->get();
    }

    // ---------- Dashboard ----------

    public function dashboard(): View
    {
        $people = User::with('role')->where('active', true)->get();
        $week = [today(), today()->addDays(6)];

        return view('backend.bunkhouse.dashboard', [
            'stats' => [
                'employees' => $people->whereNull('company')->count(),
                'external' => $people->whereNotNull('company')->count(),
                'companies' => $people->whereNotNull('company')->pluck('company')->unique()->count(),
                'outToday' => ErmTimeOff::outBetween(today(), today())->count(),
                'reviewsDue' => ErmReview::where('status', '!=', 'complete')->where('due_on', '<=', today()->addDays(30))->count(),
                'reviewsOverdue' => ErmReview::where('status', '!=', 'complete')->where('due_on', '<', today())->count(),
                'trainingOverdue' => ErmEnrollment::whereNull('completed_on')->where('due_on', '<', today())->count(),
                'openCases' => ErmCase::where('status', '!=', 'closed')->count(),
                'pendingTimeOff' => ErmTimeOff::where('status', 'requested')->count(),
            ],
            'byDept' => $people->groupBy(fn ($u) => $u->company ? '3rd party' : ($u->department ?? 'No department'))->map(fn ($g, $d) => ['label' => $d, 'value' => $g->count()])->sortByDesc('value')->values(),
            'out' => ErmTimeOff::with('user')->outBetween(...$week)->orderBy('starts_on')->get(),
            'reviews' => ErmReview::with(['user', 'reviewer'])->where('status', '!=', 'complete')->orderBy('due_on')->limit(8)->get(),
            'kudos' => ErmKudo::with(['from', 'to'])->latest()->limit(6)->get(),
            'endings' => $people->whereNotNull('access_ends_on')->filter(fn ($u) => $u->access_ends_on->lte(today()->addDays(90)))->sortBy('access_ends_on')->values(),
            'anniversaries' => $people->filter(fn ($u) => $u->hired_on && $u->hired_on->year < now()->year && $u->hired_on->copy()->year(now()->year)->between(today(), today()->addDays(45)))
                ->sortBy(fn ($u) => $u->hired_on->format('md'))->values(),
        ]);
    }

    // ---------- People ----------

    public function index(Request $request): View
    {
        $f = $request->validate(['group' => ['nullable', 'string', 'max:120'], 'q' => ['nullable', 'string', 'max:100']]);
        $all = User::with(['role', 'manager'])->withCount('reports')->where('active', true)->orderBy('name')->get();
        $groups = $all->map(fn ($u) => $u->company ?? ($u->department ?? 'No department'))->unique()->sort()->values();
        $list = $all->when($f['group'] ?? null, fn ($c, $g) => $c->filter(fn ($u) => ($u->company ?? $u->department) === $g))
            ->when($f['q'] ?? null, fn ($c, $q) => $c->filter(fn ($u) => str_contains(strtolower($u->name.' '.$u->title.' '.$u->email), strtolower($q))));

        return view('backend.bunkhouse.people', ['list' => $list->values(), 'groups' => $groups, 'f' => $f,
            'former' => User::where('active', false)->orderBy('name')->get(),
            'out' => ErmTimeOff::outBetween(today(), today())->pluck('user_id')->all()]);
    }

    public function person(User $user): View
    {
        $user->load(['role', 'manager', 'reports']);

        return view('backend.bunkhouse.person', ['u' => $user,
            'reviews' => ErmReview::with('reviewer')->where('user_id', $user->id)->latest('due_on')->get(),
            // Their own one-on-ones and the ones they held with their team
            'checkins' => ErmCheckin::with(['manager', 'user'])->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('manager_id', $user->id))->latest('held_on')->limit(8)->get(),
            'training' => ErmEnrollment::with('course')->where('user_id', $user->id)->orderByRaw('completed_on is not null')->orderBy('due_on')->get(),
            'timeOff' => ErmTimeOff::with('approver')->where('user_id', $user->id)->latest('starts_on')->limit(10)->get(),
            'kudos' => ErmKudo::with('from')->where('to_id', $user->id)->latest()->limit(10)->get(),
            'cases' => ErmCase::where('user_id', $user->id)->latest()->get(),
            'checklist' => ErmChecklistItem::with('doer')->where('user_id', $user->id)->orderBy('kind')->orderBy('position')->get()->groupBy('kind'),
            'ptoUsed' => (float) ErmTimeOff::where('user_id', $user->id)->where('type', 'PTO')->where('status', 'approved')->whereYear('starts_on', now()->year)->sum('hours'),
        ]);
    }

    // ---------- Reviews ----------

    public function reviews(): View
    {
        $open = ErmReview::with(['user', 'reviewer'])->where('status', '!=', 'complete')->orderBy('due_on')->get();

        return view('backend.bunkhouse.reviews', [
            'overdue' => $open->filter->overdue()->values(),
            'open' => $open->reject->overdue()->values(),
            'done' => ErmReview::with(['user', 'reviewer'])->where('status', 'complete')->latest('completed_at')->limit(25)->get(),
            'ratings' => ErmReview::where('status', 'complete')->whereNotNull('rating')->selectRaw('rating, count(*) n')->groupBy('rating')->pluck('n', 'rating'),
        ]);
    }

    public function reviewForm(Request $request): View
    {
        return view('backend.bunkhouse.review-new', ['people' => $this->people(), 'pick' => $request->query('user')]);
    }

    public function scheduleReview(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'], 'user_ids.*' => ['exists:users,id'],
            'kind' => ['required', Rule::in(ErmReview::KINDS)],
            'period' => ['required', 'string', 'max:30'],
            'due_on' => ['required', 'date'],
        ], ['user_ids.required' => 'Pick at least one person.']);
        foreach (User::whereIn('id', $data['user_ids'])->get() as $u) {
            ErmReview::create(['user_id' => $u->id, 'reviewer_id' => $u->manager_id, 'kind' => $data['kind'], 'period' => $data['period'], 'due_on' => $data['due_on'], 'status' => 'scheduled']);
        }

        return redirect()->route('bunkhouse.reviews')->with('status', count($data['user_ids']).' '.str('review')->plural(count($data['user_ids'])).' scheduled');
    }

    public function review(ErmReview $review): View
    {
        $review->load(['user.manager', 'reviewer']);

        return view('backend.bunkhouse.review', ['r' => $review,
            'past' => ErmReview::where('user_id', $review->user_id)->whereKeyNot($review->id)->where('status', 'complete')->latest('completed_at')->get(),
            'training' => ErmEnrollment::with('course')->where('user_id', $review->user_id)->get(),
            'kudos' => ErmKudo::where('to_id', $review->user_id)->where('created_at', '>=', now()->subYear())->count()]);
    }

    public function saveReview(Request $request, ErmReview $review): RedirectResponse
    {
        abort_if($review->status === 'complete' && ! $request->boolean('reopen'), 422);
        if ($request->boolean('reopen')) {
            $review->update(['status' => 'manager', 'completed_at' => null]);

            return back()->with('status', 'Review reopened');
        }
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(ErmReview::STATUSES))],
            'rating' => ['nullable', 'required_if:status,complete', 'integer', 'between:1,5'],
            'self_review' => ['nullable', 'string', 'max:5000'],
            'strengths' => ['nullable', 'required_if:status,complete', 'string', 'max:5000'],
            'growth' => ['nullable', 'string', 'max:5000'],
            'goals' => ['nullable', 'string', 'max:5000'],
        ], ['rating.required_if' => 'Pick a rating to complete the review.', 'strengths.required_if' => 'Add strengths to complete the review.']);
        $review->update($data + ['completed_at' => $data['status'] === 'complete' ? now() : null]);

        return redirect()->route('bunkhouse.reviews.show', $review)->with('status', $data['status'] === 'complete' ? 'Review completed' : 'Review saved');
    }

    // ---------- Check-ins ----------

    public function checkins(): View
    {
        $people = User::with('manager')->where('active', true)->whereNotNull('manager_id')->get();
        $last = ErmCheckin::selectRaw('user_id, max(held_on) as last')->groupBy('user_id')->pluck('last', 'user_id');

        return view('backend.bunkhouse.checkins', [
            'due' => $people->filter(fn ($u) => ! isset($last[$u->id]) || Carbon::parse($last[$u->id])->lt(today()->subDays(30)))
                ->sortBy(fn ($u) => $last[$u->id] ?? '0000')->values(),
            'last' => $last,
            'recent' => ErmCheckin::with(['user', 'manager'])->latest('held_on')->latest('id')->limit(30)->get(),
            'moods' => ErmCheckin::where('held_on', '>=', today()->subDays(60))->selectRaw('mood, count(*) n')->groupBy('mood')->pluck('n', 'mood'),
        ]);
    }

    public function checkinForm(Request $request): View
    {
        return view('backend.bunkhouse.checkin-new', ['people' => $this->people(), 'pick' => $request->query('user')]);
    }

    public function saveCheckin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'held_on' => ['required', 'date', 'before_or_equal:today'],
            'mood' => ['nullable', 'integer', 'between:1,5'],
            'topics' => ['required', 'string', 'max:3000'],
            'action_items' => ['nullable', 'string', 'max:3000'],
        ], ['user_id.required' => 'Pick who the check-in was with.']);
        $c = ErmCheckin::create($data + ['manager_id' => $request->user()->id]);

        return redirect()->route('bunkhouse.checkins.show', $c)->with('status', 'Check-in saved');
    }

    public function checkin(ErmCheckin $checkin): View
    {
        $checkin->load(['user', 'manager']);

        return view('backend.bunkhouse.checkin', ['c' => $checkin,
            'others' => ErmCheckin::with('manager')->where('user_id', $checkin->user_id)->whereKeyNot($checkin->id)->latest('held_on')->limit(10)->get()]);
    }

    // ---------- Training ----------

    public function training(): View
    {
        $courses = ErmCourse::withCount(['enrollments', 'enrollments as done_count' => fn ($q) => $q->whereNotNull('completed_on'),
            'enrollments as overdue_count' => fn ($q) => $q->whereNull('completed_on')->where('due_on', '<', today())])->orderBy('category')->orderBy('name')->get();

        return view('backend.bunkhouse.training', ['courses' => $courses,
            'overdue' => ErmEnrollment::with(['course', 'user'])->whereNull('completed_on')->where('due_on', '<', today())->orderBy('due_on')->get()]);
    }

    public function course(ErmCourse $course): View
    {
        $course->load(['enrollments' => fn ($q) => $q->with('user')->orderByRaw('completed_on is not null')->orderBy('due_on')]);
        $enrolled = $course->enrollments->whereNull('completed_on')->pluck('user_id')->all();

        return view('backend.bunkhouse.course', ['c' => $course,
            'missing' => $this->people()->filter(fn ($u) => $course->requiredFor($u) && ! $course->enrollments->contains('user_id', $u->id))->values(),
            'people' => $this->people()->reject(fn ($u) => in_array($u->id, $enrolled, true))->values()]);
    }

    public function assignCourse(Request $request, ErmCourse $course): RedirectResponse
    {
        $data = $request->validate(['user_ids' => ['required', 'array', 'min:1'], 'user_ids.*' => ['exists:users,id'], 'due_on' => ['required', 'date', 'after_or_equal:today']],
            ['user_ids.required' => 'Pick at least one person.']);
        $n = 0;
        foreach ($data['user_ids'] as $id) {
            // Skip anyone who still has it open
            if (! ErmEnrollment::where('course_id', $course->id)->where('user_id', $id)->whereNull('completed_on')->exists()) {
                ErmEnrollment::create(['course_id' => $course->id, 'user_id' => $id, 'assigned_on' => today(), 'due_on' => $data['due_on']]);
                $n++;
            }
        }

        return back()->with('status', $n.' '.str('person')->plural($n).' assigned');
    }

    public function completeEnrollment(Request $request, ErmEnrollment $enrollment): RedirectResponse
    {
        $data = $request->validate(['score' => ['nullable', 'integer', 'between:0,100']]);
        $enrollment->update($enrollment->completed_on ? ['completed_on' => null, 'score' => null] : ['completed_on' => today(), 'score' => $data['score'] ?? null]);

        return back()->with('status', $enrollment->completed_on ? 'Marked complete' : 'Marked not complete');
    }

    // ---------- Kudos ----------

    public function kudos(): View
    {
        return view('backend.bunkhouse.kudos', [
            'kudos' => ErmKudo::with(['from', 'to'])->latest()->paginate(30),
            'people' => $this->people(),
            'top' => ErmKudo::with('to')->where('created_at', '>=', now()->subDays(90))->get()->groupBy('to_id')->map(fn ($g) => [$g->first()->to, $g->count()])->sortByDesc(1)->take(8)->values(),
            'byValue' => ErmKudo::where('created_at', '>=', now()->subDays(90))->selectRaw('value, count(*) n')->groupBy('value')->pluck('n', 'value'),
        ]);
    }

    public function giveKudos(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'to_id' => ['required', 'exists:users,id', Rule::notIn([$request->user()->id])],
            'value' => ['required', Rule::in(ErmKudo::VALUES)],
            'message' => ['required', 'string', 'max:500'],
        ], ['to_id.required' => 'Pick who it\'s for.', 'to_id.not_in' => 'Kudos go to someone else.']);
        ErmKudo::create($data + ['from_id' => $request->user()->id]);

        return back()->with('status', 'Kudos sent');
    }

    // ---------- Time off ----------

    public function timeOff(): View
    {
        return view('backend.bunkhouse.time-off', [
            'pending' => ErmTimeOff::with('user.manager')->where('status', 'requested')->orderBy('starts_on')->get(),
            'out' => ErmTimeOff::with('user')->outBetween(today(), today()->addDays(13))->orderBy('starts_on')->get(),
            'decided' => ErmTimeOff::with(['user', 'approver'])->whereIn('status', ['approved', 'denied'])->latest('decided_at')->limit(25)->get(),
            'days' => collect(range(0, 13))->map(fn ($i) => today()->addDays($i)),
        ]);
    }

    public function timeOffForm(Request $request): View
    {
        return view('backend.bunkhouse.time-off-new', ['people' => $this->people(), 'pick' => $request->query('user')]);
    }

    public function requestTimeOff(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'type' => ['required', Rule::in(ErmTimeOff::TYPES)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'hours' => ['nullable', 'numeric', 'min:1', 'max:400'],
            'note' => ['nullable', 'string', 'max:250'],
        ], ['user_id.required' => 'Pick who is taking time off.']);
        // Default: 8 hours a weekday
        $data['hours'] ??= 8 * collect(Carbon::parse($data['starts_on'])->daysUntil(Carbon::parse($data['ends_on'])))->filter(fn ($d) => $d->isWeekday())->count();
        ErmTimeOff::create($data + ['status' => 'requested']);

        return redirect()->route('bunkhouse.time-off')->with('status', 'Time off requested');
    }

    public function decideTimeOff(Request $request, ErmTimeOff $timeOff): RedirectResponse
    {
        $do = $request->validate(['do' => ['required', Rule::in(['approved', 'denied'])]])['do'];
        abort_unless($timeOff->status === 'requested', 422);
        $timeOff->update(['status' => $do, 'approver_id' => $request->user()->id, 'decided_at' => now()]);

        return back()->with('status', 'Time off '.$do);
    }

    // ---------- HR cases ----------

    public function cases(): View
    {
        $all = ErmCase::with(['user', 'assignee'])->withCount('updates')->latest()->get();

        return view('backend.bunkhouse.cases', [
            'open' => $all->where('status', '!=', 'closed')->sortBy(fn ($c) => [array_search($c->priority, ['High', 'Medium', 'Low'], true), $c->created_at])->values(),
            'closed' => $all->where('status', 'closed')->sortByDesc('closed_at')->take(25)->values(),
            'byType' => $all->where('created_at', '>=', now()->subYear())->countBy('type')->sortDesc(),
        ]);
    }

    public function caseForm(Request $request): View
    {
        return view('backend.bunkhouse.case-new', ['people' => $this->people(), 'handlers' => $this->handlers(), 'pick' => $request->query('user')]);
    }

    /** People who can handle cases: anyone with Bunkhouse. */
    private function handlers(): Collection
    {
        return $this->people()->filter(fn ($u) => $u->hasPerm('bunkhouse'))->values();
    }

    public function openCase(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'type' => ['required', Rule::in(ErmCase::TYPES)],
            'subject' => ['required', 'string', 'max:200'],
            'priority' => ['required', Rule::in(ErmCase::PRIORITIES)],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'body' => ['required', 'string', 'max:5000'],
        ], ['user_id.required' => 'Pick who the case is about.']);
        $case = ErmCase::create(collect($data)->except('body')->all() + ['opened_by' => $request->user()->id, 'status' => 'open']);
        $case->updates()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);

        return redirect()->route('bunkhouse.cases.show', $case)->with('status', 'Case opened');
    }

    public function showCase(ErmCase $case): View
    {
        $case->load(['user.manager', 'opener', 'assignee', 'updates' => fn ($q) => $q->with('user')->oldest()]);

        return view('backend.bunkhouse.case', ['c' => $case, 'handlers' => $this->handlers(),
            'others' => ErmCase::where('user_id', $case->user_id)->whereKeyNot($case->id)->latest()->get()]);
    }

    public function updateCase(Request $request, ErmCase $case): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(ErmCase::STATUSES))],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'body' => ['nullable', 'required_if:status,closed', 'string', 'max:5000'],
        ], ['body.required_if' => 'Write how it was resolved.']);
        $closing = $data['status'] === 'closed' && $case->status !== 'closed';
        $case->update(['status' => $data['status'], 'assigned_to' => $data['assigned_to'] ?? $case->assigned_to,
            'closed_at' => $data['status'] === 'closed' ? ($case->closed_at ?? now()) : null, 'resolution' => $closing ? $data['body'] : ($data['status'] === 'closed' ? $case->resolution : null)]);
        if (filled($data['body'] ?? null)) {
            $case->updates()->create(['user_id' => $request->user()->id, 'body' => ($closing ? 'Closed: ' : '').$data['body']]);
        }

        return back()->with('status', $closing ? 'Case closed' : 'Case updated');
    }

    // ---------- Onboarding & offboarding ----------

    public function lifecycle(): View
    {
        $items = ErmChecklistItem::with(['user', 'doer'])->orderBy('position')->get()->groupBy(['kind', 'user_id']);
        $summary = fn ($kind) => collect($items[$kind] ?? [])->map(fn ($list) => ['user' => $list->first()->user, 'items' => $list, 'done' => $list->whereNotNull('done_at')->count(),
            'next' => $list->whereNull('done_at')->sortBy('due_on')->first(), 'start' => $list->min('due_on')])
            ->sortBy(fn ($r) => [$r['done'] === $r['items']->count() ? 1 : 0, $r['next']?->due_on])->values();

        return view('backend.bunkhouse.lifecycle', ['onboarding' => $summary('onboarding'), 'offboarding' => $summary('offboarding'),
            'people' => $this->people()->merge(User::where('active', false)->get())->sortBy('name')->values(),
            'endingSoon' => User::where('active', true)->whereNotNull('access_ends_on')->where('access_ends_on', '<=', today()->addDays(60))
                ->whereDoesntHave('checklistItems', fn ($q) => $q->where('kind', 'offboarding'))->orderBy('access_ends_on')->get()]);
    }

    public function startChecklist(Request $request): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'exists:users,id'], 'kind' => ['required', Rule::in(['onboarding', 'offboarding'])], 'date' => ['required', 'date']],
            ['user_id.required' => 'Pick the person.', 'date.required' => 'Pick their start or last day.']);
        $u = User::findOrFail($data['user_id']);
        if (ErmChecklistItem::where('user_id', $u->id)->where('kind', $data['kind'])->whereNull('done_at')->exists()) {
            return back()->withErrors(['user_id' => $u->name.' already has an open '.$data['kind'].' checklist.']);
        }
        ErmChecklistItem::start($u, $data['kind'], Carbon::parse($data['date']));

        return redirect()->route('bunkhouse.people.show', $u)->with('status', ucfirst($data['kind']).' checklist started for '.$u->name);
    }

    public function toggleChecklistItem(Request $request, ErmChecklistItem $item): RedirectResponse
    {
        $item->update($item->done_at ? ['done_at' => null, 'done_by' => null] : ['done_at' => now(), 'done_by' => $request->user()->id]);

        return back()->with('status', $item->done_at ? 'Done: '.$item->task : 'Reopened: '.$item->task);
    }
}
