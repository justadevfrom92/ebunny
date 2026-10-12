<?php

namespace App\Http\Controllers\Frontend\MyAccount;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\PlanGroup;
use App\Models\PlanTerm;
use App\Models\ScheduledPayment;
use App\Models\WorkItem;
use App\Services\AccountActions;
use App\Services\Catalog;
use App\Services\Products;
use App\Services\Rewards;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * My Account: the customer's own view of their account. Changes go through the
 * same services Corral uses (AccountActions, Products, Rewards), so agents see
 * every change in the account's notes and history.
 */
class PortalController extends Controller
{
    /** The signed-in My Account login. */
    private function login(Request $request): Customer
    {
        return $request->user('customer');
    }

    /** The property (account) being viewed: the login itself, or one of its linked properties picked in the switcher. */
    private function c(Request $request): Customer
    {
        $login = $this->login($request);
        $id = $request->session()->get('ma_property');

        return $id && $id !== $login->id ? ($login->properties()->firstWhere('id', $id) ?? $login) : $login;
    }

    /** Payment methods usable for a property: its own and the login's (a business pays every location from one card). */
    private function methodsFor(Request $request, Customer $c)
    {
        return PaymentMethod::whereIn('customer_id', array_unique([$c->id, $this->login($request)->id]))->whereNull('removed_at')->orderByDesc('autopay')->get();
    }

    /** Switch to another property under this login; ?to= picks the page to land on (pay, bills, dashboard). */
    public function switchProperty(Request $request, string $account): RedirectResponse
    {
        $p = $this->login($request)->properties()->firstWhere('account', $account);
        abort_unless($p, 404);
        $request->session()->put('ma_property', $p->id);
        $to = in_array($request->input('to'), ['pay', 'bills', 'dashboard'], true) ? $request->input('to') : 'dashboard';

        return redirect()->route('myaccount.'.$to)->with('status', 'Now viewing '.$p->propertyLabel().' (account '.$p->account.').');
    }

    private function actions(Request $request): AccountActions
    {
        return new AccountActions($this->c($request), null, 'MyAccount');
    }

    public function dashboard(Request $request): View
    {
        $c = $this->c($request)->load(['plan', 'bills', 'paymentMethods']);

        return view('frontend.myaccount.dashboard', [
            'c' => $c,
            'lastBill' => $c->bills->sortByDesc('billed_on')->first(),
            'term' => $c->planTerms()->where('status', 'current')->first() ?? $c->planTerms()->latest('ordered_at')->first(),
            'usage' => $c->bills->sortBy('billed_on')->take(-12)->values(),
            'messages' => $c->contactLogs()->limit(3)->get(),
        ]);
    }

    public function bills(Request $request): View
    {
        $c = $this->c($request);

        return view('frontend.myaccount.bills', ['c' => $c, 'bills' => $c->bills()->orderByDesc('billed_on')->get(), 'payments' => $c->payments()->orderByDesc('paid_on')->limit(24)->get()]);
    }

    public function insights(Request $request): View
    {
        $c = $this->c($request);
        $bills = $c->bills()->orderBy('billed_on')->get();

        return view('frontend.myaccount.insights', ['c' => $c, 'bills' => $bills->take(-12)->values(), 'avg' => $bills->avg('kwh'), 'max' => max(1, (int) $bills->max('kwh'))]);
    }

    // ---------- Pay bill / payment methods ----------

    /** Pay Bill: pick an amount (balance, past due, last bill or another amount) and pay now or at a date and time. */
    public function payForm(Request $request): View
    {
        $c = $this->c($request);

        return view('frontend.myaccount.pay', ['c' => $c, 'methods' => $this->methodsFor($request, $c), 'amounts' => $this->amounts($c),
            'scheduled' => $c->scheduledPayments()->with('method')->where('status', 'scheduled')->get(),
            'recent' => $c->scheduledPayments()->with('method')->where('status', '!=', 'scheduled')->latest('updated_at')->limit(5)->get(),
            'properties' => $this->login($request)->properties()]);
    }

    /** The amounts offered on Pay Bill: key => [label, amount, note]. */
    private function amounts(Customer $c): array
    {
        $last = $c->bills()->orderByDesc('billed_on')->first();
        $pastDue = $c->due_date && $c->due_date->isPast() ? max(0, (float) $c->balance) : 0;

        return array_filter([
            'balance' => $c->balance > 0 ? ['Current balance', (float) $c->balance, $c->due_date ? 'Due '.$c->due_date->format('M j, Y') : null] : null,
            'past_due' => $pastDue > 0 ? ['Past due amount', $pastDue, 'Pay this to avoid a late fee or disconnection'] : null,
            'last_bill' => $last ? ['Last bill', (float) $last->amount, 'Billed '.$last->billed_on->format('M j, Y')] : null,
            'other' => ['Another amount', null, 'Pay part of your balance, or extra toward your next bill'],
        ]);
    }

    public function pay(Request $request): RedirectResponse
    {
        $c = $this->c($request);
        $amounts = $this->amounts($c);
        $data = $request->validate([
            'choice' => ['required', Rule::in(array_keys($amounts))],
            'amount' => ['nullable', 'required_if:choice,other', 'numeric', 'min:1', 'max:10000'],
            'method' => ['required', Rule::in($this->methodsFor($request, $c)->pluck('id')->all())],
            'when' => ['required', 'in:now,later'],
            'pay_date' => ['nullable', 'required_if:when,later', 'date', 'after_or_equal:today', 'before_or_equal:'.today()->addDays(60)->toDateString()],
            'pay_time' => ['nullable', 'required_if:when,later', 'date_format:H:i'],
        ], ['pay_date.before_or_equal' => 'Pick a date within the next 60 days.', 'amount.required_if' => 'Enter the amount to pay.']);
        $amount = $data['choice'] === 'other' ? round((float) $data['amount'], 2) : $amounts[$data['choice']][1];

        if ($data['when'] === 'later') {
            $at = Carbon::parse($data['pay_date'].' '.$data['pay_time']);
            if ($at->lte(now()->addMinutes(5))) {
                return back()->withInput()->withErrors(['pay_time' => 'Pick a time at least a few minutes from now, or choose Pay now.']);
            }
            $c->scheduledPayments()->create(['payment_method_id' => $data['method'], 'amount' => $amount, 'pay_at' => $at]);
            $c->notes()->create(['author' => 'Customer (MyAccount)', 'category' => 'Payment', 'action' => 'Scheduled Payment', 'priority' => 'Low',
                'body' => 'Scheduled a $'.number_format($amount, 2).' payment for '.$at->format('M j, Y g:i A').' in My Account.']);

            return redirect()->route('myaccount.pay')->with('status', 'Payment of $'.number_format($amount, 2).' scheduled for '.$at->format('l, M j \a\t g:i A').'. You can cancel it here until then.');
        }
        [$message] = DB::transaction(fn () => $this->actions($request)->run('make-payment', ['amount' => $amount, 'method' => $data['method']]));

        return redirect()->route('myaccount.bills')->with('status', $message);
    }

    public function cancelScheduled(Request $request, ScheduledPayment $scheduled): RedirectResponse
    {
        abort_unless($scheduled->customer_id === $this->c($request)->id && $scheduled->status === 'scheduled', 404);
        $scheduled->update(['status' => 'canceled']);

        return back()->with('status', 'Scheduled payment of $'.number_format($scheduled->amount, 2).' canceled.');
    }

    public function methods(Request $request): View
    {
        $c = $this->c($request);

        return view('frontend.myaccount.methods', ['c' => $c, 'methods' => $c->paymentMethods()->whereNull('removed_at')->get(),
            'cards' => collect(config('admin.integrations.stripe.env'))->every(fn ($v) => filled($v))]);
    }

    public function removeMethod(Request $request, PaymentMethod $method): RedirectResponse
    {
        abort_unless($method->customer_id === $this->c($request)->id, 404);
        $method->update(['removed_at' => now(), 'autopay' => false]);

        return back()->with('status', 'Payment method removed.');
    }

    public function defaultMethod(Request $request, PaymentMethod $method): RedirectResponse
    {
        $c = $this->c($request);
        abort_unless($method->customer_id === $c->id && ! $method->removed_at, 404);
        PaymentMethod::where('customer_id', $c->id)->update(['autopay' => false]);
        $method->update(['autopay' => true]);

        return back()->with('status', $method->label().' is now used for AutoPay.');
    }

    // ---------- AutoPay, Paperless, Peak Perks, Pick Your Due Date ----------

    /** Products: every product on one page, the same card for each. */
    public function products(Request $request): View
    {
        $c = $this->c($request);
        $on = $c->products()->whereNull('removed_at')->pluck('product')->all();

        return view('frontend.myaccount.products', ['c' => $c, 'products' => config('myaccount.products'), 'on' => $on,
            'hasMethod' => $this->methodsFor($request, $c)->isNotEmpty()]);
    }

    /** The old one-product pages land on that product's card. */
    public function product(string $product): RedirectResponse
    {
        abort_unless(config('myaccount.products.'.$product), 404);

        return redirect()->to(route('myaccount.products').'#'.$product);
    }

    public function toggleProduct(Request $request, string $product): RedirectResponse
    {
        [$name, , , $how] = config('myaccount.products.'.$product) ?? abort(404);
        abort_unless($how === 'self', 404);
        $c = $this->c($request);
        $on = $request->validate(['enroll' => ['required', 'boolean']])['enroll'];
        if ($on && $name === 'AutoPay' && $this->methodsFor($request, $c)->isEmpty()) {
            return back()->withErrors(['enroll' => 'Add a payment method before turning on AutoPay.']);
        }
        $on ? Products::add($c, $name) : Products::remove($c, $name);
        $c->notes()->create(['author' => 'Customer (MyAccount)', 'category' => 'Products', 'action' => ($on ? 'Add ' : 'Remove ').$name, 'priority' => 'Low',
            'body' => ($on ? 'Enrolled in ' : 'Un-enrolled from ').$name.' in My Account.']);

        return redirect()->to(route('myaccount.products').'#'.$product)->with('status', $on ? 'You\'re enrolled in '.$name.'.' : $name.' is turned off.');
    }

    // ---------- Profile, password, authorized users, linked accounts ----------

    public function profile(Request $request): View
    {
        return view('frontend.myaccount.profile', ['c' => $this->c($request)]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $c = $this->c($request);
        $c->update($request->validate([
            'email' => ['required', 'email', 'max:200'],
            'phone' => ['required', 'regex:/^\D*(\d\D*){10}$/'],
            'phone_type' => ['required', Rule::in(['mobile', 'landline', 'voip'])],
            'language' => ['required', Rule::in(['English', 'Spanish'])],
            'billing_street' => ['required', 'string', 'max:200'],
            'billing_city' => ['required', 'string', 'max:100'],
            'billing_state' => ['required', 'string', 'size:2'],
            'billing_zip' => ['required', 'digits:5'],
        ]) + ['marketing_opt_in' => $request->boolean('marketing_opt_in')]);

        return back()->with('status', 'Profile saved.');
    }

    public function password(Request $request): RedirectResponse
    {
        $c = $this->login($request);
        $data = $request->validate(['current_password' => ['required'], 'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed']]);
        if (! Hash::check($data['current_password'], $c->password)) {
            return back()->withErrors(['current_password' => 'Your current password isn\'t right.']);
        }
        $c->update(['password' => $data['password']]);

        return back()->with('status', 'Password changed.');
    }

    public function authorizedUser(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'phone' => ['required', 'string', 'max:20']]);
        [$message] = $this->actions($request)->run('add-authorized-user', $data);

        return back()->with('status', $message);
    }

    public function removeAuthorizedUser(Request $request, int $index): RedirectResponse
    {
        $c = $this->c($request);
        $users = $c->authorized_users ?? [];
        abort_unless(isset($users[$index]), 404);
        $name = $users[$index]['name'];
        array_splice($users, $index, 1);
        $c->update(['authorized_users' => $users]);

        return back()->with('status', $name.' removed.');
    }

    public function linkAccount(Request $request): RedirectResponse
    {
        $c = $this->login($request);
        $data = $request->validate(['account' => ['required', 'string', 'max:20'], 'zip' => ['required', 'digits:5']]);
        $other = Customer::where('account', $data['account'])->where('zip', $data['zip'])->whereRaw('lower(email) = ?', [strtolower($c->email)])->first();
        if (! $other || $other->is($c)) {
            return back()->withErrors(['account' => 'To link an account it must have the same email as this one.']);
        }
        $c->update(['linked_accounts' => array_values(array_unique([...($c->linked_accounts ?? []), $other->account]))]);

        return back()->with('status', 'Account '.$other->account.' linked.');
    }

    // ---------- Plan: current plan, renew, transfer service ----------

    public function plan(Request $request, Catalog $catalog): View
    {
        $c = $this->c($request)->load('plan');
        $rates = $catalog->currentRates();
        $fee = $catalog->currentFees()[$c->market_id] ?? null;
        // Renewal offers: Lando → Plan Groups "renew" (business accounts: "business")
        $group = PlanGroup::where('slug', $c->type === 'Residential' ? 'renew' : 'business')->first();
        $offers = ($group ? $group->plans()->where('active', true)->where('term', '>', 1)->get() : collect())
            ->filter(fn ($p) => isset($rates[$p->id.'-'.$c->market_id]) && $fee)
            ->map(fn ($p) => ['plan' => $p, 'price' => Catalog::averagePrice($rates[$p->id.'-'.$c->market_id]->energy, $fee->per_kwh, $fee->per_bill, $p->mrc, 1000)])->values();

        return view('frontend.myaccount.plan', ['c' => $c, 'term' => $c->planTerms()->where('status', 'current')->first(),
            'pending' => $c->planTerms()->where('status', 'pending')->with('plan')->first(), 'offers' => $offers]);
    }

    public function renew(Request $request): RedirectResponse
    {
        $c = $this->c($request);
        $data = $request->validate(['plan_id' => ['required', Rule::exists('plans', 'id')->where('active', true)], 'agree' => ['accepted']]);
        $plan = Plan::find($data['plan_id']);
        $current = $c->planTerms()->where('status', 'current')->first();
        $start = $current?->contract_end && $current->contract_end->isFuture() ? $current->contract_end->copy()->addDay() : today()->addDay();
        DB::transaction(function () use ($c, $plan, $start) {
            PlanTerm::where('customer_id', $c->id)->where('status', 'pending')->delete();
            PlanTerm::create(['customer_id' => $c->id, 'plan_id' => $plan->id, 'rate_class' => now()->format('Ymd').'_F_'.$plan->internal, 'status' => 'pending',
                'ordered_at' => now(), 'contract_start' => $start, 'contract_end' => $start->copy()->addMonths($plan->term), 'energy_charge' => 0, 'rate_2000' => 0]);
            WorkItem::create(['queue' => 'unapplied-plan-changes', 'customer_id' => $c->id, 'summary' => 'Renewal from My Account: '.$plan->internal]);
            $c->notes()->create(['author' => 'Customer (MyAccount)', 'category' => 'Order', 'action' => 'Renewal', 'priority' => 'Low',
                'body' => 'Renewed to '.$plan->name.' ('.$plan->internal.') in My Account, starting '.$start->toDateString().'.']);
        });

        return redirect()->route('myaccount.plan')->with('status', 'You\'re renewed on '.$plan->name.', starting '.$start->format('M j, Y').'.');
    }

    public function transferForm(Request $request): View
    {
        return view('frontend.myaccount.transfer', ['c' => $this->c($request)]);
    }

    public function transfer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'street' => ['required', 'string', 'max:200'], 'city' => ['required', 'string', 'max:100'], 'zip' => ['required', 'digits:5'],
            'esiid' => ['nullable', 'regex:/^\d{17,22}$/'], 'date' => ['required', 'date', 'after:today', 'before:+90 days'],
        ]);
        [$message] = DB::transaction(fn () => $this->actions($request)->run('transfer-service', $data + ['esiid' => $data['esiid'] ?? null]));

        return redirect()->route('myaccount.dashboard')->with('status', $message);
    }

    // ---------- Rewards, refer a friend, message center ----------

    public function rewards(Request $request): View
    {
        $c = $this->c($request);

        return view('frontend.myaccount.rewards', ['c' => $c, 'offers' => Rewards::offers(), 'history' => $c->starEntries()->latest()->limit(30)->get()]);
    }

    public function redeem(Request $request): RedirectResponse
    {
        $data = $request->validate(['offer' => ['required', 'integer', 'min:0']]);
        try {
            return back()->with('status', Rewards::redeem($this->c($request), (int) $data['offer']));
        } catch (RuntimeException $e) {
            return back()->withErrors(['offer' => $e->getMessage()]);
        }
    }

    public function refer(Request $request): View
    {
        $c = $this->c($request);

        return view('frontend.myaccount.refer', ['c' => $c, 'referrals' => $c->referral_code ? Customer::where('referred_by', $c->referral_code)->get(['first_name', 'status', 'created_at']) : collect()]);
    }

    public function messages(Request $request): View
    {
        return view('frontend.myaccount.messages', ['c' => $this->c($request), 'messages' => $this->c($request)->contactLogs()->limit(50)->get()]);
    }

    // ---------- QuickPay (no sign-in) ----------

    public function quickpay(Request $request): View
    {
        $c = null;
        if ($request->filled('account')) {
            $request->validate(['account' => ['required', 'string', 'max:20'], 'zip' => ['required', 'digits:5']]);
            $c = Customer::where('account', $request->query('account'))->where('zip', $request->query('zip'))->first();
        }

        return view('frontend.myaccount.quickpay', ['c' => $c, 'looked' => $request->filled('account')]);
    }
}
