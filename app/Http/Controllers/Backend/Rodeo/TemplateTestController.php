<?php

namespace App\Http\Controllers\Backend\Rodeo;

use App\Http\Controllers\Controller;
use App\Mail\TemplateTest;
use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\EmailSuppression;
use App\Models\EmailTemplate;
use App\Models\EmailTestSend;
use App\Models\Market;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Email Templates → Send Test: its own page for one template. Pick who gets it
 * (yourself, typed addresses, account numbers, your Corral bookmarks, an admin
 * team or a customer category), check the list, preview the email as a given
 * account sees it, then really send it marked as a test. Every test is kept
 * with its per-recipient result and can be sent again.
 */
class TemplateTestController extends Controller
{
    /** A test never goes to more people than this. */
    public const MAX = 25;

    public const MODES = [
        'me' => 'Just me',
        'emails' => 'Email addresses',
        'accounts' => 'Account numbers',
        'bookmarks' => 'My bookmarks',
        'team' => 'An admin team',
        'category' => 'Customer category',
    ];

    /** Mailers that only write to the log; nothing reaches an inbox until MAIL_MAILER is set in .env. */
    public static function delivers(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }

    public function show(Request $request, EmailTemplate $template): View
    {
        $statusFilter = $request->query('result');

        return view('backend.rodeo.template-test', [
            'template' => $template,
            'sample' => Customer::with('plan')->orderBy('id')->first(),
            'roles' => Role::withCount(['users' => fn ($q) => $q->where('active', true)])->orderBy('name')->get(),
            'bookmarks' => $request->user()->bookmarks()->whereNotNull('email')->get(['customers.id', 'name', 'account', 'email']),
            'markets' => Market::orderBy('name')->get(),
            'delivers' => self::delivers(),
            'tests' => $template->testSends()->with('user')->when($statusFilter, fn ($q, $s) => $q->where('status', $s))->latest('id')->paginate(15)->withQueryString(),
            'counts' => $template->testSends()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'statusFilter' => $statusFilter,
        ]);
    }

    /** "Check recipients": who a send would go to, without sending. */
    public function recipientsPreview(Request $request, EmailTemplate $template): JsonResponse
    {
        try {
            $data = $this->validated($request);
            [$recipients, $target] = $this->recipients($request, $data);
        } catch (ValidationException $e) {
            return response()->json(['error' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json([
            'target' => $target,
            'count' => $recipients->count(),
            'max' => self::MAX,
            'recipients' => $recipients->take(100)->map(fn ($r) => ['name' => $r['name'], 'email' => $r['email'],
                'account' => $r['is_customer'] ? $r['customer']->account : null, 'fills' => $r['is_customer'] ? 'their account' : 'sample '.$r['customer']->account])->values(),
        ]);
    }

    /** The email as one account would get it, for the preview pane. */
    public function preview(Request $request, EmailTemplate $template): JsonResponse
    {
        $data = $request->validate(['sample' => ['nullable', 'string', 'max:20'], 'prefix' => ['nullable', 'string', 'max:30'], 'note' => ['nullable', 'string', 'max:500']]);
        $c = $this->sample($data['sample'] ?? null);
        [$subject, $html] = $this->compose($template, $c, $data['prefix'] ?? '[TEST]', $data['note'] ?? null, $request->user());

        return response()->json(['subject' => $subject, 'html' => $html, 'customer' => ['name' => $c->name, 'account' => $c->account, 'found' => $c->account === ($data['sample'] ?? $c->account)]]);
    }

    public function send(Request $request, EmailTemplate $template): RedirectResponse
    {
        return $this->perform($request, $template, $this->validated($request));
    }

    /** Send a past test again to the same people with the same options. */
    public function again(Request $request, EmailTemplate $template, EmailTestSend $send): RedirectResponse
    {
        abort_unless($send->email_template_id === $template->id && $send->params, 404);

        return $this->perform($request, $template, $send->params);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'mode' => ['required', Rule::in(array_keys(self::MODES))],
            'emails' => ['required_if:mode,emails', 'nullable', 'string', 'max:2000'],
            'accounts' => ['required_if:mode,accounts', 'nullable', 'string', 'max:2000'],
            'role_id' => ['required_if:mode,team', 'nullable', 'exists:roles,id'],
            'status' => ['nullable', 'array'], 'status.*' => [Rule::in(array_keys(config('admin.customer_statuses')))],
            'type' => ['nullable', Rule::in(['Residential', 'Small Business'])],
            'market_id' => ['nullable', 'exists:markets,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX],
            'sample' => ['nullable', 'exists:customers,account'],
            'prefix' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:500'],
            'log_contact' => ['nullable', 'boolean'],
        ], ['sample.exists' => 'No account with that number for the fill-ins.']);
    }

    private function perform(Request $request, EmailTemplate $template, array $data): RedirectResponse
    {
        [$recipients, $target] = $this->recipients($request, $data);
        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages(['mode' => 'Nobody with an email address matches that.']);
        }
        if ($recipients->count() > self::MAX) {
            throw ValidationException::withMessages(['mode' => $recipients->count().' people match. A test goes to '.self::MAX.' at most; narrow it down.']);
        }

        $delivers = self::delivers();
        $prefix = $data['prefix'] ?? '[TEST]';
        $logContact = (bool) ($data['log_contact'] ?? true);
        $log = [];
        foreach ($recipients as $r) {
            /** @var Customer $c */
            $c = $r['customer'];
            [$subject, $body] = $this->compose($template, $c, $prefix, $data['note'] ?? null, $request->user());
            if (EmailSuppression::has($r['email'])) {
                $log[] = array_filter(['email' => $r['email'], 'name' => $r['name'], 'account' => $r['is_customer'] ? $c->account : null, 'status' => 'suppressed', 'error' => 'On the suppression list']);

                continue;
            }
            try {
                Mail::to($r['email'], $r['name'])->send(new TemplateTest($subject, $body));
                $status = $delivers ? 'sent' : 'logged';
                $error = null;
            } catch (\Throwable $e) {
                $status = 'failed';
                $error = substr($e->getMessage(), 0, 200);
            }
            // Customers see the test on their account's Contact Log
            if ($r['is_customer'] && $logContact) {
                ContactLog::create(['customer_id' => $c->id, 'channel' => 'Email', 'template' => 'Test: '.$template->name, 'body' => $body,
                    'status' => $status === 'sent' ? 'sent' : 'not sent', 'sent_at' => $status === 'sent' ? now() : null, 'user_id' => $request->user()->id]);
            }
            $log[] = array_filter(['email' => $r['email'], 'name' => $r['name'], 'account' => $r['is_customer'] ? $c->account : null,
                'fills' => $r['is_customer'] ? null : $c->account, 'status' => $status, 'error' => $error]);
        }

        $statuses = array_unique(array_column($log, 'status'));
        $overall = count($statuses) === 1 ? $statuses[0] : 'partial';
        EmailTestSend::create(['email_template_id' => $template->id, 'user_id' => $request->user()->id, 'mode' => $data['mode'], 'target' => $target,
            'subject' => $prefix.' '.$template->subject, 'note' => $data['note'] ?? null,
            'params' => array_intersect_key($data, array_flip(['mode', 'emails', 'accounts', 'role_id', 'status', 'type', 'market_id', 'limit', 'sample', 'prefix', 'note', 'log_contact'])),
            'recipients' => $log, 'status' => $overall, 'created_at' => now()]);

        $n = count($log);
        $msg = match ($overall) {
            'sent' => "Test sent to $n ".str('recipient')->plural($n).'.',
            'logged' => "Test written to the mail log for $n ".str('recipient')->plural($n).' — not delivered, because MAIL_MAILER in .env is "'.config('mail.default').'". Set it to smtp (or ses, postmark…) with its MAIL_* settings to deliver.',
            'suppressed' => 'Nothing sent: every recipient is on the suppression list (Rodeo → Emails → Suppression List).',
            'failed' => 'The test could not be sent: '.($log[0]['error'] ?? 'mail error').'. Check the MAIL_* settings in .env.',
            default => 'Test sent to some recipients; see Test History for the ones that failed.',
        };

        return redirect()->route('rodeo.templates.test', $template)->with('test_result', ['ok' => $overall === 'sent', 'message' => $msg]);
    }

    /** Subject and HTML exactly as sent: fill-ins for $c, the test prefix, and a banner saying who sent the test. */
    private function compose(EmailTemplate $template, Customer $c, string $prefix, ?string $note, User $sender): array
    {
        $banner = '<div style="background:#fff3d6;color:#8a5a00;padding:10px 14px;font:14px/1.4 Arial,sans-serif;border-bottom:1px solid #f0d58a">'
            .'<b>Test email</b> sent by '.e($sender->name).' from Rodeo'.($note ? ': '.e($note) : '.').'</div>';

        return [trim($prefix.' '.$template->renderSubject($c)), $banner.$template->render($c)];
    }

    private function sample(?string $account): Customer
    {
        return Customer::with('plan')->where('account', $account)->first() ?? Customer::with('plan')->orderBy('id')->firstOrFail();
    }

    /** @return array{0: Collection<int, array{email: string, name: ?string, customer: Customer, is_customer: bool}>, 1: ?string} */
    private function recipients(Request $request, array $data): array
    {
        // Fill-ins for people who aren't customers come from the sample account
        $sample = $this->sample($data['sample'] ?? null);
        $person = fn (string $email, ?string $name) => ['email' => $email, 'name' => $name, 'customer' => $sample, 'is_customer' => false];
        $customer = fn (Customer $c) => ['email' => $c->email, 'name' => $c->name, 'customer' => $c, 'is_customer' => true];
        $list = fn (?string $s) => collect(preg_split('/[\s,;]+/', (string) $s, -1, PREG_SPLIT_NO_EMPTY))->unique()->values();

        switch ($data['mode']) {
            case 'me':
                return [collect([$person($request->user()->email, $request->user()->name)]), $request->user()->email];
            case 'emails':
                $emails = $list($data['emails'] ?? '');
                $bad = $emails->reject(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
                if ($bad->isNotEmpty()) {
                    throw ValidationException::withMessages(['emails' => 'Not an email address: '.$bad->implode(', ')]);
                }

                return [$emails->map(fn ($e) => $person($e, null)), $emails->implode(', ')];
            case 'accounts':
                $accounts = $list($data['accounts'] ?? '');
                $found = Customer::with('plan')->whereIn('account', $accounts)->get();
                $missing = $accounts->diff($found->pluck('account'));
                if ($missing->isNotEmpty()) {
                    throw ValidationException::withMessages(['accounts' => 'No account '.$missing->implode(', ')]);
                }

                return [$found->filter(fn ($c) => $c->email)->map($customer)->values(), $accounts->implode(', ')];
            case 'bookmarks':
                return [$request->user()->bookmarks()->with('plan')->whereNotNull('email')->get()->map($customer)->values(), 'Bookmarks of '.$request->user()->name];
            case 'team':
                $role = Role::findOrFail($data['role_id']);

                return [User::where('role_id', $role->id)->where('active', true)->orderBy('name')->get()->map(fn ($u) => $person($u->email, $u->name)), $role->name.' team'];
            default:
                $statuses = array_filter((array) ($data['status'] ?? []));
                $q = Customer::with('plan')->whereNotNull('email')
                    ->when($statuses, fn ($q, $s) => $q->whereIn('status', $s))
                    ->when($data['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
                    ->when($data['market_id'] ?? null, fn ($q, $m) => $q->where('market_id', $m));
                $target = collect([$statuses ? implode(', ', $statuses) : 'Any status', $data['type'] ?? null, isset($data['market_id']) ? Market::find($data['market_id'])?->name : null])->filter()->implode(' · ');

                // Same people for Check Recipients and Send: a stable order, not random
                return [$q->orderBy('id')->limit((int) ($data['limit'] ?? 5))->get()->map($customer)->values(), $target];
        }
    }
}
