<?php

namespace App\Http\Controllers\Backend\Corral;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Market;
use App\Models\Plan;
use App\Models\WorkItem;
use App\Services\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Phone enrollments (residential and business) and renew/change plan for an existing account. */
class OrderController extends Controller
{
    public function create(Request $request): View
    {
        return $this->form($request, false);
    }

    public function createBiz(Request $request): View
    {
        return $this->form($request, true);
    }

    private function form(Request $request, bool $biz): View
    {
        $renew = $request->query('renew') ? Customer::where('account', $request->query('renew'))->firstOrFail() : null;
        $prefill = $renew ?? ($request->query('esiid') ? Customer::where('esiid', $request->query('esiid'))->first() : null);
        $biz = $biz || $renew?->type === 'Small Business';

        return view('backend.corral.order', [
            'biz' => $biz,
            'renew' => $renew,
            'c' => $prefill,
            'markets' => Market::orderBy('name')->get(),
            'plans' => Plan::where('active', true)->where('type', $biz ? 'Biz' : 'Resi')->orderBy('term')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'renew' => ['nullable', 'exists:customers,account'],
            'biz' => ['boolean'],
            'business_name' => ['nullable', 'required_if:biz,1', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:200'],
            'address' => ['required', 'string', 'max:200'],
            'city' => ['required', 'string', 'max:100'],
            'zip' => ['required', 'digits:5'],
            'esiid' => ['nullable', 'string', 'max:40'],
            'market_id' => ['required', 'exists:markets,id'],
            'plan_id' => ['required', Rule::exists('plans', 'id')->where('active', true)],
            'enrollment_type' => ['required', Rule::in(['Switch', 'Move-In', 'Self-Selected Switch', 'Renewal'])],
            'start_date' => ['nullable', 'date', 'after_or_equal:today'],
            'autopay' => ['boolean'],
            'paperless' => ['boolean'],
            'peak_perks' => ['boolean'],
            'source' => ['required', Rule::in(['Phone', 'Website', 'Referral', 'Power to Choose'])],
        ]);
        $plan = Plan::find($data['plan_id']);
        $user = $request->user();

        $customer = DB::transaction(function () use ($data, $plan, $user) {
            if (! empty($data['renew'])) {
                $c = Customer::where('account', $data['renew'])->firstOrFail();
                $old = $c->plan?->name ?? 'no plan';
                $c->update(['plan_id' => $plan->id, 'enrollment_type' => 'Renewal']);
                $c->notes()->create(['user_id' => $user->id, 'author' => $user->name, 'disposition' => 'Billing question',
                    'body' => 'Plan changed from '.$old.' to '.$plan->name.' ('.$plan->internal.').']);
                WorkItem::create(['queue' => 'unapplied-plan-changes', 'customer_id' => $c->id, 'summary' => 'Send plan change to billing']);

                return $c;
            }

            return Enrollment::create(($data['biz'] ?? false) ? ['name' => $data['business_name']] + $data : $data, $data['source'], $user,
                'Order taken by '.$user->name.' via '.$data['source'].($data['biz'] ?? false ? ' (contact: '.$data['name'].')' : '').'.');
        });

        return redirect()->route('corral.customers.show', $customer)
            ->with('status', ! empty($data['renew']) ? 'Plan changed' : 'Order submitted for account '.$customer->account);
    }
}
