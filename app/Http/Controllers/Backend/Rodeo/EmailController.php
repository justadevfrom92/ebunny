<?php

namespace App\Http\Controllers\Backend\Rodeo;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\EmailCategory;
use App\Models\EmailSuppression;
use App\Models\EmailTemplate;
use App\Models\EmailTestSend;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Rodeo → Emails: how emails are doing by template, every email sent to customers,
 * all test sends, template categories and the suppression list.
 */
class EmailController extends Controller
{
    public const STAGES = ['queued' => 'Queued', 'sent' => 'Delivered', 'opened' => 'Opened', 'clicked' => 'Clicked', 'dropped' => 'Dropped', 'not sent' => 'Not sent', 'suppressed' => 'Suppressed'];

    private static function emails(): Builder
    {
        return ContactLog::where('channel', 'Email');
    }

    public function overview(Request $request): View
    {
        $days = in_array((int) $request->query('days'), [7, 30, 90, 365], true) ? (int) $request->query('days') : 30;
        $since = today()->subDays($days - 1);
        $q = fn () => self::emails()->where('created_at', '>=', $since);
        $rate = fn ($n, $of) => $of ? round(100 * $n / $of, 1) : 0;

        $rows = $q()->selectRaw("template, count(*) as total,
                sum(case when sent_at is not null then 1 else 0 end) as delivered,
                sum(case when opened_at is not null then 1 else 0 end) as opened,
                sum(case when clicked_at is not null then 1 else 0 end) as clicked,
                sum(case when dropped_at is not null then 1 else 0 end) as dropped,
                sum(case when status in ('not sent', 'suppressed') then 1 else 0 end) as unsent,
                max(created_at) as last_at")
            ->groupBy('template')->orderByDesc('total')->get();
        $templates = EmailTemplate::with('category')->get()->keyBy('name');
        $total = $rows->sum('total');
        $delivered = $rows->sum('delivered');

        return view('backend.rodeo.emails.overview', [
            'days' => $days,
            'rows' => $rows,
            'templates' => $templates,
            'stats' => [
                'total' => $total, 'delivered' => $delivered,
                'open' => $rate($rows->sum('opened'), $delivered), 'click' => $rate($rows->sum('clicked'), $delivered),
                'drop' => $rate($rows->sum('dropped'), $total), 'unsent' => $rows->sum('unsent'),
            ],
            'chart' => self::chart($q()->selectRaw('date(created_at) as d, count(*) as n')->groupBy('d')->pluck('n', 'd'), $since, $days),
            'since' => $since,
            'tests' => EmailTestSend::where('created_at', '>=', $since)->count(),
            'suppressed' => EmailSuppression::count(),
            'recent' => self::emails()->with('customer')->latest()->latest('id')->limit(8)->get(),
            'byCategory' => EmailCategory::withCount('templates')->orderBy('position')->get()
                ->map(fn ($c) => [$c, $rows->filter(fn ($r) => ($templates[$r->template] ?? null)?->email_category_id === $c->id)->sum('total')]),
        ]);
    }

    /** Emails per day (7 or 30 days), per week (90) or per month (365): [[label, title, count], …]. */
    private static function chart($perDay, $since, int $days): array
    {
        $step = match (true) {
            $days <= 30 => 1, $days <= 90 => 7, default => 0
        };
        $out = [];
        if ($step === 0) {
            for ($m = $since->copy()->startOfMonth(); $m <= today(); $m->addMonth()) {
                $n = $perDay->filter(fn ($v, $d) => str_starts_with($d, $m->format('Y-m')))->sum();
                $out[] = [$m->format('M'), $m->format('F Y'), $n];
            }

            return $out;
        }
        for ($d = $since->copy(); $d <= today(); $d->addDays($step)) {
            $n = collect(range(0, $step - 1))->sum(fn ($i) => $perDay[$d->copy()->addDays($i)->toDateString()] ?? 0);
            $out[] = [$d->format($step === 1 ? 'j' : 'n/j'), ($step === 1 ? '' : 'week of ').$d->format('M j'), $n];
        }

        return $out;
    }

    public function sent(Request $request): View
    {
        $f = $request->only(['account', 'email', 'template', 'stage', 'campaign', 'user', 'start', 'end']);
        $q = self::emails()->with(['customer', 'campaign', 'user'])
            ->when($f['account'] ?? null, fn ($q, $a) => $q->whereHas('customer', fn ($c) => $c->where('account', 'like', trim($a).'%')))
            ->when($f['email'] ?? null, fn ($q, $e) => $q->whereHas('customer', fn ($c) => $c->where('email', 'like', '%'.trim($e).'%')))
            ->when($f['template'] ?? null, fn ($q, $t) => $q->where('template', $t))
            ->when($f['campaign'] ?? null, fn ($q, $c) => $q->where('campaign_id', $c))
            ->when($f['user'] ?? null, fn ($q, $u) => $u === 'system' ? $q->whereNull('user_id') : $q->where('user_id', $u))
            ->when($f['start'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($f['end'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($f['stage'] ?? null, fn ($q, $s) => match ($s) {
                'dropped' => $q->whereNotNull('dropped_at'),
                'clicked' => $q->whereNotNull('clicked_at')->whereNull('dropped_at'),
                'opened' => $q->whereNotNull('opened_at')->whereNull('clicked_at')->whereNull('dropped_at'),
                'sent' => $q->whereNotNull('sent_at')->whereNull('opened_at')->whereNull('dropped_at'),
                default => $q->whereNull('sent_at')->where('status', $s),
            });

        return view('backend.rodeo.emails.sent', [
            'f' => $f,
            'emails' => $q->latest()->latest('id')->paginate(50)->withQueryString(),
            'templateNames' => self::emails()->distinct()->orderBy('template')->pluck('template'),
            'campaigns' => Campaign::where('channel', 'Email')->orderByDesc('id')->get(['id', 'name']),
            'users' => User::whereIn('id', self::emails()->whereNotNull('user_id')->distinct()->select('user_id'))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(ContactLog $email): View
    {
        abort_unless($email->channel === 'Email', 404);
        $email->load(['customer.plan', 'campaign', 'user']);
        $template = EmailTemplate::where('name', preg_replace('/^Test: /', '', $email->template))->first();

        return view('backend.rodeo.emails.show', [
            'email' => $email,
            'template' => $template,
            // What the customer got: the stored body, or else the template filled in for them
            'html' => $email->body ? (str_contains($email->body, '<') ? $email->body : nl2br(e($email->body))) : ($template && $email->customer ? $template->render($email->customer) : null),
            'suppression' => $email->customer?->email ? EmailSuppression::where('email', strtolower($email->customer->email))->first() : null,
            'others' => self::emails()->where('customer_id', $email->customer_id)->whereKeyNot($email->id)->latest('id')->limit(10)->get(),
        ]);
    }

    public function tests(Request $request): View
    {
        $f = $request->only(['template', 'result', 'user', 'start', 'end']);

        return view('backend.rodeo.emails.tests', [
            'f' => $f,
            'tests' => EmailTestSend::with(['user', 'template'])
                ->when($f['template'] ?? null, fn ($q, $t) => $q->where('email_template_id', $t))
                ->when($f['result'] ?? null, fn ($q, $r) => $q->where('status', $r))
                ->when($f['user'] ?? null, fn ($q, $u) => $q->where('user_id', $u))
                ->when($f['start'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                ->when($f['end'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
                ->latest('id')->paginate(30)->withQueryString(),
            'templates' => EmailTemplate::orderBy('name')->get(['id', 'name']),
            'users' => User::whereIn('id', EmailTestSend::select('user_id'))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    // ---------- Categories ----------

    public function categories(): View
    {
        return view('backend.rodeo.emails.categories', [
            'categories' => EmailCategory::with(['templates' => fn ($q) => $q->orderBy('name')])->withCount('templates')->orderBy('position')->orderBy('name')->get(),
            'uncategorized' => EmailTemplate::whereNull('email_category_id')->orderBy('name')->get(),
        ]);
    }

    public function categoryForm(?EmailCategory $category = null): View
    {
        return view('backend.rodeo.emails.category-form', [
            'category' => $category ?? new EmailCategory(['color' => '#00AEEF']),
            'templates' => EmailTemplate::with('category')->orderBy('name')->get(),
        ]);
    }

    public function saveCategory(Request $request, ?EmailCategory $category = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('email_categories')->ignore($category)],
            'description' => ['nullable', 'string', 'max:200'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'position' => ['nullable', 'integer', 'min:0', 'max:999'],
            'templates' => ['array'],
            'templates.*' => ['integer', 'exists:email_templates,id'],
        ]);
        $data['position'] ??= $category?->position ?? (int) EmailCategory::max('position') + 1;
        $ids = $data['templates'] ?? [];
        unset($data['templates']);
        $category ? $category->update($data) : $category = EmailCategory::create($data);
        // The ticked templates are filed here; ones unticked here become uncategorized
        EmailTemplate::where('email_category_id', $category->id)->whereNotIn('id', $ids)->get()->each->update(['email_category_id' => null]);
        EmailTemplate::whereIn('id', $ids)->where(fn ($q) => $q->whereNull('email_category_id')->orWhere('email_category_id', '!=', $category->id))->get()->each->update(['email_category_id' => $category->id]);

        return redirect()->route('rodeo.emails.categories')->with('status', 'Category saved');
    }

    public function deleteCategory(EmailCategory $category): RedirectResponse
    {
        $category->delete();   // its templates become uncategorized

        return back()->with('status', 'Category deleted; its templates are now uncategorized');
    }

    // ---------- Suppression list ----------

    public function suppressions(Request $request): View
    {
        $f = $request->only(['q', 'reason']);

        return view('backend.rodeo.emails.suppressions', [
            'f' => $f,
            'list' => EmailSuppression::with(['customer', 'user'])
                ->when($f['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('email', 'like', '%'.strtolower(trim($s)).'%')->orWhereHas('customer', fn ($c) => $c->where('account', 'like', trim($s).'%'))))
                ->when($f['reason'] ?? null, fn ($q, $r) => $q->where('reason', $r))
                ->latest('id')->paginate(50)->withQueryString(),
            'counts' => EmailSuppression::selectRaw('reason, count(*) as n')->groupBy('reason')->pluck('n', 'reason'),
        ]);
    }

    public function suppressionForm(Request $request): View
    {
        return view('backend.rodeo.emails.suppression-form', ['email' => $request->query('email')]);
    }

    public function saveSuppression(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150', Rule::unique('email_suppressions')],
            'reason' => ['required', Rule::in(array_keys(EmailSuppression::REASONS))],
            'note' => ['nullable', 'string', 'max:250'],
        ], ['email.unique' => 'That address is already on the suppression list.']);
        $data['email'] = strtolower(trim($data['email']));
        EmailSuppression::create($data + ['customer_id' => Customer::where('email', $data['email'])->value('id'), 'user_id' => $request->user()->id]);

        return redirect()->route('rodeo.emails.suppressions')->with('status', $data['email'].' added; no email will go to it.');
    }

    public function deleteSuppression(EmailSuppression $suppression): RedirectResponse
    {
        $suppression->delete();

        return back()->with('status', $suppression->email.' removed; emails can go to it again.');
    }
}
