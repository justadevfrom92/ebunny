<?php

namespace App\Http\Controllers\Backend\Corral;

use App\Http\Controllers\Controller;
use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\CustomerFile;
use App\Models\CustomerFlag;
use App\Models\CustomerProduct;
use App\Models\ErcotTransaction;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PlanTerm;
use App\Models\QueueLog;
use App\Models\ServiceAddress;
use App\Services\AccountActions;
use App\Services\Products;
use App\Services\Rewards;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Everything you can do from a Corral account page besides status and notes. */
class AccountController extends Controller
{
    private function back(Customer $c, string $section, string $status): RedirectResponse
    {
        return redirect(route('corral.customers.show', $c).'#'.$section)->with('status', $status);
    }

    // ---------- Actions menus ----------

    /** The action's form, or a confirmation when it has nothing to fill in. */
    public function actionForm(Customer $customer, string $action): View
    {
        abort_unless(AccountActions::exists($action), 404);
        $def = config('corral.actions.status')[$action] ?? config('corral.actions.service')[$action];

        return view('backend.corral.account.action', ['c' => $customer, 'action' => $action, 'label' => $def[0], 'fields' => $def[1],
            'methods' => $customer->paymentMethods()->whereNull('removed_at')->get(), 'payments' => $customer->payments()->get()]);
    }

    public function runAction(Request $request, Customer $customer, string $action): RedirectResponse
    {
        abort_unless(AccountActions::exists($action), 404);
        $def = config('corral.actions.status')[$action] ?? config('corral.actions.service')[$action];
        if (isset($def[2])) {
            Gate::authorize($def[2]);
        }
        $rules = [];
        foreach ($def[1] as $name => [$label, $type]) {
            $rules[$name] = match ($type) {
                'money' => ['required', 'numeric', 'min:0.01', 'max:100000'],
                'number' => ['required', 'integer', 'min:1', 'max:100000'],
                'date' => ['required', 'date'],
                'textarea' => ['required', 'string', 'max:2000'],
                'method' => ['required', Rule::exists('payment_methods', 'id')->where('customer_id', $customer->id)->whereNull('removed_at')],
                'payment' => ['required', Rule::exists('payments', 'id')->where('customer_id', $customer->id)],
                'template' => ['required', Rule::exists('email_templates', 'name')],
                default => ['required', 'string', 'max:200'],
            };
        }
        $input = $request->validate($rules);

        [$message, $url] = DB::transaction(fn () => (new AccountActions($customer, $request->user()))->run($action, $input));

        return $url ? redirect($url)->with('status', $message) : $this->back($customer, 'actions', $message);
    }

    // ---------- Flags ----------

    public function addFlag(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate(['flag' => ['required', Rule::in(config('corral.flags'))]]);
        CustomerFlag::create(['customer_id' => $customer->id, 'flag' => $data['flag'], 'user_id' => $request->user()->id, 'author' => $request->user()->name]);

        return $this->back($customer, 'flags', 'Flag added: '.$data['flag']);
    }

    public function removeFlag(CustomerFlag $flag): RedirectResponse
    {
        $flag->update(['removed_at' => now()]);

        return $this->back($flag->customer, 'flags', 'Flag removed');
    }

    // ---------- Products ----------

    public function addProduct(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate(['product' => ['required', Rule::in(array_keys(config('corral.products')))]]);
        if (! Products::add($customer, $data['product'], $request->user())) {
            return $this->back($customer, 'products', $data['product'].' is already on the account');
        }

        return $this->back($customer, 'products', $data['product'].' added');
    }

    public function removeProduct(CustomerProduct $product): RedirectResponse
    {
        Products::remove($product->customer, $product->product);

        return $this->back($product->customer, 'products', $product->product.' removed');
    }

    // ---------- Credits / debits / payment methods ----------

    public function deleteLedger(LedgerEntry $entry): RedirectResponse
    {
        $entry->update(['status' => 'deleted']);

        return $this->back($entry->customer, 'balances', 'Pending '.$entry->kind.' deleted');
    }

    public function setAutopay(PaymentMethod $method): RedirectResponse
    {
        PaymentMethod::where('customer_id', $method->customer_id)->where('id', '!=', $method->id)->update(['autopay' => false]);
        $method->update(['autopay' => true]);
        $method->customer->update(['autopay' => true]);

        return $this->back($method->customer, 'payment-methods', 'AutoPay set to '.$method->label());
    }

    public function removeMethod(PaymentMethod $method): RedirectResponse
    {
        $method->update(['removed_at' => now(), 'autopay' => false]);

        return $this->back($method->customer, 'payment-methods', 'Payment method removed');
    }

    // ---------- Files ----------

    public function uploadFile(Request $request, Customer $customer): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,txt,csv,doc,docx']]);
        $file = $request->file('file');
        $path = $file->store('customer-files/'.$customer->account, 'local');
        CustomerFile::create(['customer_id' => $customer->id, 'name' => $file->getClientOriginalName(), 'kind' => 'upload', 'path' => $path]);

        return $this->back($customer, 'files', 'File uploaded');
    }

    public function downloadFile(CustomerFile $file): StreamedResponse
    {
        abort_unless($file->path && Storage::disk('local')->exists($file->path), 404, 'This document is produced by the billing system and is not stored here.');

        return Storage::disk('local')->download($file->path, $file->name);
    }

    // ---------- Marketing details ----------

    public function updateMarketing(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validate([
            'channel' => ['required', 'string', 'max:60'],
            'msid' => ['required', 'string', 'max:60'],
            'promo_code' => ['nullable', 'string', 'max:40'],
        ]));

        return $this->back($customer, 'details', 'Marketing details saved');
    }

    // ---------- Stars ----------

    public function stars(Customer $customer): View
    {
        return view('backend.corral.account.stars', ['c' => $customer, 'offers' => Rewards::offers()]);
    }

    public function redeem(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate(['offer' => ['required', 'integer', 'min:0']]);
        try {
            $message = Rewards::redeem($customer, (int) $data['offer'], $request->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['offer' => $e->getMessage()]);
        }

        return redirect()->route('corral.customers.stars', $customer)->with('status', $message);
    }

    public function recalculateStars(Customer $customer): RedirectResponse
    {
        $customer->syncStars();

        return $this->back($customer, 'stars', 'Stars recalculated: '.number_format($customer->stars));
    }

    // ---------- Queues (TECH) ----------

    public function addQueue(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate(['queue' => ['required', Rule::in(config('corral.queues'))]]);
        QueueLog::create(['customer_id' => $customer->id, 'queue' => $data['queue'], 'entered_at' => now(), 'user_id' => $request->user()->id]);

        return $this->back($customer, 'tech', 'Added to '.$data['queue']);
    }

    public function moveQueue(Request $request, QueueLog $log): RedirectResponse
    {
        $data = $request->validate(['queue' => ['required', 'string']]);
        $log->update(['exited_at' => now()]);
        if ($data['queue'] !== 'remove') {
            abort_unless(in_array($data['queue'], config('corral.queues'), true), 422);
            QueueLog::create(['customer_id' => $log->customer_id, 'queue' => $data['queue'], 'entered_at' => now(), 'user_id' => $request->user()->id]);
        }

        return $this->back($log->customer, 'tech', $data['queue'] === 'remove' ? 'Removed from '.$log->queue : 'Moved to '.$data['queue']);
    }

    // ---------- Plan change log / address change log / ERCOT ----------

    public function planTermAction(Request $request, PlanTerm $term): RedirectResponse
    {
        $data = $request->validate([
            'do' => ['required', Rule::in(['activate', 'welcome', 'new-welcome', 'edit-rate'])],
            'energy_charge' => ['required_if:do,edit-rate', 'nullable', 'numeric', 'min:0', 'max:1'],
        ]);
        $c = $term->customer;
        $msg = match ($data['do']) {
            'activate' => DB::transaction(function () use ($term, $c) {
                PlanTerm::where('customer_id', $c->id)->where('status', 'current')->update(['status' => 'ended', 'contract_end' => today()->subDay()]);
                $term->update(['status' => 'current', 'contract_start' => today()]);
                $c->update(['plan_id' => $term->plan_id, 'rate_class' => $term->rate_class]);

                return 'Activated '.$term->rate_class;
            }),
            'welcome', 'new-welcome' => (function () use ($c, $data) {
                CustomerFile::create(['customer_id' => $c->id, 'name' => 'welcome-packet-'.$c->ticket.'.pdf', 'kind' => 'welcome']);
                ContactLog::create(['customer_id' => $c->id, 'channel' => 'Email', 'template' => $data['do'] === 'welcome' ? 'Welcome Letter' : 'Welcome Letter - Renewed', 'status' => 'queued']);

                return 'Welcome packet queued';
            })(),
            'edit-rate' => tap('Energy charge set to '.$data['energy_charge'], fn () => $term->update(['energy_charge' => $data['energy_charge']])),
        };

        return $this->back($c, 'plans', $msg);
    }

    public function updateAddress(Request $request, ServiceAddress $address): RedirectResponse
    {
        $address->update($request->validate([
            'service_start' => ['nullable', 'date'],
            'service_end' => ['nullable', 'date', 'after_or_equal:service_start'],
        ]));

        return $this->back($address->customer, 'plans', 'Service dates updated');
    }

    public function ercotAction(Request $request, ErcotTransaction $tx): RedirectResponse
    {
        $data = $request->validate(['do' => ['required', Rule::in(['unlink', 'cancel'])]]);
        $tx->update(['status' => $data['do'] === 'unlink' ? 'unlinked' : 'cancelled']);

        return $this->back($tx->customer, 'ercot', 'Transaction '.$tx->trans_type.' '.($data['do'] === 'unlink' ? 'unlinked' : 'cancelled'));
    }

    // ---------- Contact log / API log ----------

    public function contactLog(Customer $customer): View
    {
        return view('backend.corral.account.contact-log', ['c' => $customer, 'logs' => $customer->contactLogs()->get(), 'calls' => $customer->phonecalls()->with('user')->get()]);
    }

    public function toggleMarketing(Customer $customer): RedirectResponse
    {
        $customer->update(['marketing_opt_in' => ! $customer->marketing_opt_in]);

        return redirect()->route('corral.customers.contact-log', $customer)->with('status', 'Marketing emails: opted '.($customer->marketing_opt_in ? 'IN' : 'OUT'));
    }

    public function apiLog(Customer $customer): View
    {
        return view('backend.corral.account.api-log', ['c' => $customer, 'logs' => $customer->apiLogs()->get()]);
    }

    /** Warehouse: the flattened reporting row for the account, like the original. */
    public static function warehouse(Customer $c): array
    {
        $bills = $c->bills;
        $current = $c->planTerms->firstWhere('status', 'current');
        $products = $c->products->pluck('product');
        $paid = $c->payments->where('status', 'Success');

        return [
            'account_number' => $c->account, 'account_status' => $c->status, 'customer_type' => $c->type, 'customer_name' => $c->name,
            'first_name' => $c->first_name, 'last_name' => $c->last_name, 'tec_score' => $c->tec_score, 'phone_1' => preg_replace('/\D/', '', (string) $c->phone),
            'email' => $c->email, 'billing_address' => trim($c->billing_street.', '.$c->billing_city.', '.$c->billing_state.' '.$c->billing_zip, ', '),
            'service_address' => $c->address.', '.$c->city.', TX '.$c->zip, 'order_date' => $c->created_at, 'requested_start_date' => $c->requested_start?->toDateString(),
            'actual_start_date' => $c->actual_start?->toDateString(), 'days_on_flow' => $c->actual_start ? (int) $c->actual_start->diffInDays(today()) : null,
            'market_name' => $c->market?->name, 'commodity' => 'e', 'uan1' => $c->esiid, 'uan2' => $c->meter_number, 'uan3' => $c->load_profile,
            'uan5' => $c->meter_type, 'load_zone' => $c->load_zone, 'plan_name' => $c->plan?->name, 'plan_rate_class' => $c->rate_class,
            'plan_energy_charge' => $current?->energy_charge, 'plan_base_charge' => $c->plan?->mrc, 'plan_promo' => $c->promo_code,
            'plan_term' => $c->plan?->term, 'plan_term_start' => $current?->contract_start?->toDateString(), 'plan_term_end' => $current?->contract_end?->toDateString(),
            'current_balance' => number_format($c->balance, 2, '.', ''), 'bills' => $bills->count(), 'bills_ontime' => $bills->where('ontime', true)->count(),
            'bills_late' => $bills->where('ontime', false)->count(), 'last_payment_date' => $paid->max('paid_on')?->toDateString(),
            'last_payment_amount' => optional($paid->sortBy('paid_on')->last())->amount, 'last_bill_date' => $bills->max('billed_on')?->toDateString(),
            'avg_usage' => $bills->count() ? (int) round($bills->avg('kwh')) : null, 'paperless' => $c->paperless ? 'y' : 'n',
            'autopay' => $c->autopay ? 'y' : 'n', 'peak_perks' => $c->peak_perks ? 'y' : 'n', 'rewards' => $products->contains('Rangler Rewards') ? 'y' : 'n',
            'giddyup' => $products->contains('Giddyup Guarantee') ? 'y' : 'n', 'freedom' => $products->contains('Freedom Flex') ? 'y' : 'n',
            'solar' => $products->contains('Solar Buyback') ? 'y' : 'n', 'warranty' => $products->contains(fn ($p) => str_contains($p, 'Protection') || str_contains($p, 'Warranty')) ? 'y' : 'n',
            'stars_balance' => $c->stars, 'msid' => $c->msid, 'channel' => $c->channel, 'is_move' => $c->move_switch === 'move' ? 'y' : 'n',
            'is_switch' => $c->move_switch === 'switch' ? 'y' : 'n', 'is_resi' => $c->type === 'Residential' ? 'y' : 'n', 'is_web' => $c->source === 'Website' ? 'y' : 'n',
            'is_call' => $c->source === 'Phone' ? 'y' : 'n', 'opt_out_marketing' => $c->marketing_opt_in ? 'n' : 'y', 'created' => $c->created_at, 'modified' => $c->updated_at,
        ];
    }
}
