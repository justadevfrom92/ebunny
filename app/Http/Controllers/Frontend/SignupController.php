<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Market;
use App\Models\Page;
use App\Models\Plan;
use App\Models\PlanGroup;
use App\Models\Signup;
use App\Models\Site;
use App\Services\Catalog;
use App\Services\Enrollment;
use App\Support\SiteContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Website sign-up (the original /checkout): service address → plan → about you →
 * review → accepted. Also the deposit, alternatives, frozen, save, start-call,
 * cancel and error pages. Orders are created by App\Services\Enrollment, the
 * same as Corral → Create Order. Text above each step comes from the matching
 * Lando page (checkout, checkout/accepted, …) when it has content.
 */
class SignupController extends Controller
{
    public const STEPS = ['address' => 'Service Address', 'plan' => 'Choose a Plan', 'about' => 'About You', 'review' => 'Review & Sign Up'];

    private function data(Request $request): array
    {
        return $request->session()->get('signup', []);
    }

    private function put(Request $request, array $values): void
    {
        $request->session()->put('signup', array_merge($this->data($request), $values));
    }

    /** The intro text editors set on the Lando page for this step (e.g. checkout/accepted). */
    private function intro(Request $request, string $path): string
    {
        $site = Site::forHost($request->getHost());
        $page = $site?->pages()->where('path', $path)->where('status', 'Published')->first();

        return $page ? SiteContent::expand($page->contentSource()->areaBlock('primary')?->html, $site) : '';
    }

    private function view(Request $request, string $step, array $with = []): View
    {
        return view('frontend.checkout.'.$step, $with + ['d' => $this->data($request), 'step' => $step, 'intro' => $this->intro($request, $step === 'address' ? 'checkout' : 'checkout/'.$step)]);
    }

    // ---------- Step 1: service address ----------

    public function start(Request $request): View
    {
        // Links like /checkout?plan=CT12&zip=77002&msid=3001&promo=BOOTS&ref=ABCD1234 pre-fill the order
        $pre = array_filter([
            'plan_code' => $request->query('plan'), 'zip' => preg_match('/^\d{5}$/', (string) $request->query('zip')) ? $request->query('zip') : null,
            'msid' => preg_replace('/\D/', '', (string) $request->query('msid')) ?: null, 'promo_code' => Str::limit(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $request->query('promo')), 40, ''),
            'referred_by' => Str::limit(preg_replace('/[^A-Za-z0-9]/', '', (string) $request->query('ref')), 20, ''),
            'biz' => $request->query('type') === 'business' ? true : null,
        ], fn ($v) => $v !== null && $v !== '');
        if ($pre) {
            $this->put($request, $pre);
        }

        return $this->view($request, 'address');
    }

    public function address(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'biz' => ['boolean'],
            'zip' => ['required', 'digits:5'],
            'address' => ['required', 'string', 'max:200'],
            'unit' => ['nullable', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:100'],
            'esiid' => ['nullable', 'regex:/^\d{17,22}$/'],
            'enrollment_type' => ['required', Rule::in(['Switch', 'Move-In'])],
            'start_date' => ['nullable', 'required_if:enrollment_type,Move-In', 'date', 'after:today', 'before:+90 days'],
        ], ['esiid.regex' => 'An ESIID is 17 to 22 digits. Leave it blank if you don\'t know it.', 'start_date.required_if' => 'Pick the day you move in.']);
        $market = Enrollment::marketForZip($data['zip']);
        if (! $market) {
            return back()->withInput()->withErrors(['zip' => 'We don\'t serve '.$data['zip'].' yet. It may be outside the deregulated Texas market.']);
        }
        $this->put($request, $data + ['market_id' => $market, 'biz' => $request->boolean('biz')]);

        return redirect()->route('checkout.plan');
    }

    // ---------- Step 2: plan ----------

    public function plans(Request $request, Catalog $catalog)
    {
        $d = $this->data($request);
        if (empty($d['market_id'])) {
            return redirect()->route('checkout');
        }
        $market = Market::find($d['market_id']);
        $rates = $catalog->currentRates();
        $fee = $catalog->currentFees()[$market->id] ?? null;
        // The plans the website offers (Lando → Plan Groups "resi" / "business"), plus the plan the visitor clicked
        $offered = PlanGroup::where('slug', ! empty($d['biz']) ? 'business' : 'resi')->first()?->plans()->pluck('plans.id') ?? collect();
        $plans = Plan::where('active', true)->where(fn ($q) => $q->whereIn('id', $offered)->orWhere('internal', $d['plan_code'] ?? '-'))
            ->orderBy('term')->orderBy('name')->get()
            ->filter(fn ($p) => isset($rates[$p->id.'-'.$market->id]) && $fee)
            ->map(fn ($p) => ['plan' => $p, 'price' => Catalog::averagePrice($rates[$p->id.'-'.$market->id]->energy, $fee->per_kwh, $fee->per_bill, $p->mrc, 1000)])
            ->values();
        $selected = $d['plan_id'] ?? $plans->first(fn ($r) => $r['plan']->internal === ($d['plan_code'] ?? null))['plan']->id ?? null;

        return $this->view($request, 'plan', ['market' => $market, 'plans' => $plans, 'selected' => $selected]);
    }

    public function plan(Request $request): RedirectResponse
    {
        $data = $request->validate(['plan_id' => ['required', Rule::exists('plans', 'id')->where('active', true)]]);
        $this->put($request, $data);

        return redirect()->route('checkout.about');
    }

    // ---------- Step 3: about you ----------

    public function aboutForm(Request $request)
    {
        return empty($this->data($request)['plan_id']) ? redirect()->route('checkout.plan') : $this->view($request, 'about');
    }

    public function about(Request $request): RedirectResponse
    {
        $biz = ! empty($this->data($request)['biz']);
        $data = $request->validate([
            'business_name' => [$biz ? 'required' : 'nullable', 'string', 'max:150'],
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:200'],
            'phone' => ['required', 'regex:/^\D*(\d\D*){10}$/'],
            'phone_type' => ['required', Rule::in(['mobile', 'landline', 'voip'])],
            'language' => ['required', Rule::in(['English', 'Spanish'])],
            'ssn_last4' => ['required', 'digits:4'],
            'credit_frozen' => ['boolean'],
            'marketing_opt_in' => ['boolean'],
            'username' => ['nullable', 'string', 'min:4', 'max:40', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:customers,username'],
            'password' => ['nullable', 'required_with:username', 'string', 'min:8', 'max:100', 'confirmed'],
        ], ['phone.regex' => 'Enter a 10-digit phone number.']);
        if ($request->boolean('credit_frozen')) {
            $this->put($request, collect($data)->except(['password', 'password_confirmation'])->all());

            return redirect()->route('checkout.frozen');
        }
        $this->put($request, $data);

        return redirect()->route('checkout.review');
    }

    // ---------- Step 4: review and submit ----------

    public function review(Request $request, Catalog $catalog)
    {
        $d = $this->data($request);
        if (empty($d['first_name'])) {
            return redirect()->route('checkout.about');
        }
        $plan = Plan::findOrFail($d['plan_id']);
        $market = Market::findOrFail($d['market_id']);
        $rate = $catalog->currentRates()[$plan->id.'-'.$market->id] ?? null;
        $fee = $catalog->currentFees()[$market->id] ?? null;
        $prices = $rate && $fee ? collect([500, 1000, 2000])->mapWithKeys(fn ($k) => [$k => Catalog::averagePrice($rate->energy, $fee->per_kwh, $fee->per_bill, $plan->mrc, $k)]) : collect();

        return $this->view($request, 'review', ['plan' => $plan, 'market' => $market, 'prices' => $prices]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $request->validate(['agree_efl' => ['accepted'], 'agree_tos' => ['accepted'], 'agree_yrac' => ['accepted'],
            'autopay' => ['boolean'], 'paperless' => ['boolean'], 'peak_perks' => ['boolean']],
            ['accepted' => 'Please confirm you have read and agree to this document.']);
        $d = $this->data($request);
        abort_if(empty($d['first_name']) || empty($d['plan_id']), 419, 'Your sign-up session expired. Please start again.');

        $name = ! empty($d['biz']) ? $d['business_name'] : $d['first_name'].' '.$d['last_name'];
        $customer = Enrollment::create($d + [
            'name' => $name, 'autopay' => $request->boolean('autopay'), 'paperless' => $request->boolean('paperless'), 'peak_perks' => $request->boolean('peak_perks'),
            'ip' => $request->ip(), 'channel' => isset($d['msid']) ? 'Website - '.$d['msid'] : 'Website',
        ], 'Website');
        $customer->update(['ssn_last4' => $d['ssn_last4']]);
        Signup::where('token', $request->session()->get('signup_token'))->update(['customer_id' => $customer->id, 'step' => 'submitted']);
        $request->session()->forget(['signup', 'signup_token']);
        $request->session()->put('signup_done', $customer->id);

        return redirect()->route('checkout.accepted');
    }

    public function accepted(Request $request)
    {
        $customer = Customer::with('plan')->find($request->session()->get('signup_done'));

        return $customer ? $this->view($request, 'accepted', ['c' => $customer]) : redirect()->route('checkout');
    }

    // ---------- Save and finish later ----------

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:200']]);
        $token = Str::random(48);
        Signup::create(['token' => $token, 'step' => $request->input('step', 'address'), 'email' => $data['email'],
            'data' => collect($this->data($request))->except(['password', 'password_confirmation', 'ssn_last4'])->all()]);
        $request->session()->put('signup_token', $token);
        $link = route('checkout.resume', $token);
        Mail::raw('Finish signing up for '.config('brand.name')." electricity:\n\n$link\n", fn ($m) => $m->to($data['email'])->subject('Finish your sign-up'));

        return redirect()->route('checkout.saved');
    }

    public function saved(Request $request): View
    {
        return $this->view($request, 'save');
    }

    public function resume(Request $request, string $token): RedirectResponse
    {
        $signup = Signup::where('token', $token)->whereNull('customer_id')->where('created_at', '>=', now()->subDays(30))->firstOrFail();
        $request->session()->put('signup', $signup->data);
        $request->session()->put('signup_token', $token);

        return redirect()->route(['address' => 'checkout', 'plan' => 'checkout.plan', 'about' => 'checkout.about', 'review' => 'checkout.about'][$signup->step] ?? 'checkout');
    }

    // ---------- Other checkout pages ----------

    public function page(Request $request, string $page): View
    {
        return $this->view($request, $page);
    }

    /** Deposit: look up the account's deposit due. Card payments need the payment processor set up. */
    public function deposit(Request $request): View
    {
        $customer = null;
        if ($request->filled('account')) {
            $request->validate(['account' => ['required', 'digits_between:6,12'], 'zip' => ['required', 'digits:5']]);
            $customer = Customer::where('account', $request->query('account'))->where('zip', $request->query('zip'))->first();
        }

        return $this->view($request, 'deposit', ['c' => $customer, 'looked' => $request->filled('account'),
            'payments' => collect(config('admin.integrations.stripe.env'))->every(fn ($v) => filled($v))]);
    }

    /** Cancel: within 3 days of signing up, before service starts (the right of rescission). */
    public function cancel(Request $request): RedirectResponse
    {
        $data = $request->validate(['account' => ['required', 'string', 'max:20'], 'zip' => ['required', 'digits:5'], 'email' => ['required', 'email']]);
        $c = Customer::where('account', $data['account'])->where('zip', $data['zip'])->whereRaw('lower(email) = ?', [strtolower($data['email'])])->first();
        if (! $c) {
            return back()->withInput()->withErrors(['account' => 'We couldn\'t find an order with those details.']);
        }
        if ($c->created_at->lt(now()->subDays(3)) || ! in_array($c->status, ['Submitted', 'Pending - Credit', 'Pending - Deposit Due', 'Pending - No Deposit Due', 'Pending - Utility Not Answered'], true)) {
            return back()->withInput()->withErrors(['account' => 'This order can no longer be cancelled online. Please call us at '.config('brand.phone').'.']);
        }
        $c->update(['status' => 'Cancelled']);
        $c->notes()->create(['author' => 'Website', 'category' => 'MVO/Cancel/Rescission', 'action' => 'Rescission', 'priority' => 'Medium', 'body' => 'Customer cancelled the order on the website (right of rescission).']);

        return redirect()->route('checkout.page', 'cancel')->with('cancelled', $c->account);
    }
}
