<?php

namespace App\Services;

use App\Models\ApiLog;
use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\CustomerFlag;
use App\Models\CustomerProduct;
use App\Models\EmailSuppression;
use App\Models\ErcotTransaction;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\PlanTerm;
use App\Models\QueueLog;
use App\Models\ReferenceRow;
use App\Models\ServiceAddress;
use App\Models\StarEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * The account actions from the Corral "-- Status --" and "-- Customer Service --"
 * menus (listed in config/corral.php). Each method changes the account and returns
 * a message, or a URL to send the agent to. Every action also leaves a note.
 *
 * Actions that need an outside service (Experian, Stripe, email, SMS) record
 * honestly that nothing was sent when that service isn't configured.
 */
class AccountActions
{
    /** $user is the admin doing it; null when the customer does it in My Account ($source 'MyAccount'). */
    public function __construct(private Customer $c, private ?User $user, private string $source = 'Phone') {}

    private function who(): string
    {
        return $this->user?->name ?? 'Customer ('.$this->source.')';
    }

    /** @return array{0: string, 1: ?string} [message, redirect URL] */
    public function run(string $action, array $input): array
    {
        $method = Str::camel($action);
        $result = $this->{$method}($input);
        [$message, $url] = is_array($result) ? $result : [$result, null];

        if ($action !== 'delete-order') {
            $label = $this->label($action);
            $this->c->notes()->create(['user_id' => $this->user?->id, 'author' => $this->who(), 'disposition' => $label,
                'category' => 'Order', 'action' => $label, 'priority' => 'Low', 'body' => $label.': '.$message]);
        }

        return [$message, $url];
    }

    public static function exists(string $action): bool
    {
        return isset(config('corral.actions.status')[$action]) || isset(config('corral.actions.service')[$action]);
    }

    public function label(string $action): string
    {
        return (config('corral.actions.status')[$action] ?? config('corral.actions.service')[$action])[0];
    }

    // ---------- helpers ----------

    private function configured(string $integration): bool
    {
        return collect(config('admin.integrations.'.$integration.'.env'))->every(fn ($v) => filled($v));
    }

    private function integrationName(string $integration): string
    {
        return config('admin.integrations.'.$integration.'.name');
    }

    /** Leave the account's current order-flow queues and enter a new one. */
    private function moveToQueue(string $queue, ?string $note = null): void
    {
        QueueLog::where('customer_id', $this->c->id)->whereNull('exited_at')
            ->where('queue', 'like', 'QueueCart%')->where('queue', 'not like', 'QueueCartException%')->where('queue', 'not like', 'QueueCartVip%')
            ->update(['exited_at' => now()]);
        QueueLog::create(['customer_id' => $this->c->id, 'queue' => $queue, 'entered_at' => now(), 'note' => $note, 'user_id' => $this->user?->id]);
    }

    private function enterQueue(string $queue, ?string $note = null): void
    {
        QueueLog::create(['customer_id' => $this->c->id, 'queue' => $queue, 'entered_at' => now(), 'note' => $note, 'user_id' => $this->user?->id]);
    }

    private function ercot(string $type, string $label, ?string $date = null, string $purpose = 'request', ?string $esiid = null): ErcotTransaction
    {
        return ErcotTransaction::create(['customer_id' => $this->c->id, 'esiid' => $esiid ?? $this->c->esiid, 'trans_date' => today(),
            'purpose' => $purpose, 'scheduled_on' => $date ? Carbon::parse($date) : null, 'trans_type' => $type, 'label' => $label]);
    }

    private function addProduct(string $product, array $data = []): void
    {
        if (! $this->c->hasProduct($product)) {
            CustomerProduct::create(['customer_id' => $this->c->id, 'product' => $product, 'data' => $data ?: null, 'user_id' => $this->user?->id]);
            if ($field = config('corral.product_fields.'.$product)) {
                $this->c->update([$field => true]);
            }
        }
    }

    private function contact(string $channel, string $template, ?string $body = null): string
    {
        $integration = $channel === 'SMS' ? 'sms' : 'salesforce';
        if ($channel === 'Email' && EmailSuppression::has($this->c->email)) {
            ContactLog::create(['customer_id' => $this->c->id, 'channel' => $channel, 'template' => $template, 'body' => $body, 'status' => 'suppressed', 'user_id' => $this->user?->id]);

            return 'Email "'.$template.'" not sent: '.$this->c->email.' is on the suppression list (Rodeo → Emails).';
        }
        $ready = $this->configured($integration);
        ContactLog::create(['customer_id' => $this->c->id, 'channel' => $channel, 'template' => $template, 'body' => $body,
            'phone' => $channel === 'SMS' ? $this->c->phone : null, 'status' => $ready ? 'queued' : 'not sent', 'sent_at' => null, 'user_id' => $this->user?->id]);

        return $ready
            ? $channel.' "'.$template.'" queued for '.$this->integrationName($integration).'.'
            : $channel.' "'.$template.'" logged but not sent: '.$this->integrationName($integration).' is not configured.';
    }

    private function findAccount(string $account): Customer
    {
        $other = Customer::where('account', trim($account))->first();
        abort_if(! $other || $other->is($this->c), 422, 'Enter a different, existing account number.');

        return $other;
    }

    // ---------- Status ----------

    public function creditRun(array $in): string
    {
        $ok = $this->configured('experian');
        ApiLog::create(['customer_id' => $this->c->id, 'api' => 'Experian', 'action' => 'credit.check', 'status' => $ok ? 'not built' : 'skipped', 'created_at' => now(),
            'request' => ['body' => ['account' => $this->c->account, 'zip' => $this->c->zip, 'ssn' => '•••• hidden']], 'response' => ['status' => $ok ? 'not built' : 'skipped', 'body' => ['message' => $ok ? 'The credit check client has not been built yet: nothing was sent.' : 'Experian is not configured: nothing was sent.']]]);

        return $ok ? 'Experian credentials are set, but the credit check client has not been built yet.' : 'Credit not run: Experian is not configured.';
    }

    public function creditMaxDeposit(array $in): string
    {
        $row = ReferenceRow::where('table_key', 'max-deposits')->get()->first(fn ($r) => str_starts_with($this->c->type, $r->cells[0] ?? '-'));
        $amount = (float) preg_replace('/[^\d.]/', '', $row->cells[1] ?? '400');
        LedgerEntry::create(['customer_id' => $this->c->id, 'kind' => 'debit', 'amount' => $amount, 'description' => 'Deposit', 'user_id' => $this->user?->id]);
        $this->c->update(['status' => 'Pending - Deposit Due']);
        $this->moveToQueue('QueueCartFulfilledDeposit');

        return 'Deposit of $'.number_format($amount, 2).' required.';
    }

    public function creditWaiveDeposit(array $in): string
    {
        $n = LedgerEntry::where('customer_id', $this->c->id)->where('kind', 'debit')->where('description', 'Deposit')->where('status', 'pending')->update(['status' => 'deleted']);
        $this->c->update(['status' => 'Pending - No Deposit Due']);

        return 'Deposit waived'.($n ? ' ('.$n.' pending deposit removed)' : '').'. Reason: '.$in['reason'];
    }

    public function sendToMarket(array $in): string
    {
        $type = $this->c->move_switch === 'move' ? ['814_16', 'MoveIn'] : ['814_01', 'Switch'];
        $this->ercot($type[0], $type[1], $in['date']);
        $this->c->update(['status' => 'Pending - Utility Not Answered', 'requested_start' => $in['date']]);
        $this->moveToQueue('QueueCartFulfilledRequested');

        return 'Enrollment ('.$type[0].' '.$type[1].') sent to ERCOT for '.$in['date'].'.';
    }

    public function tempDisconnect(array $in): string
    {
        $this->ercot('650_01', 'Disconnect for Non-Pay (temporary)', $in['date']);
        $this->c->update(['status' => 'Pending - Disconnect']);
        $this->moveToQueue('QueueCartDisconnectSent');

        return 'Temporary disconnect requested for '.$in['date'].'.';
    }

    public function disconnect(array $in): string
    {
        $this->ercot('650_01', 'Disconnect for Non-Pay', $in['date']);
        $this->c->update(['status' => 'Pending - Disconnect']);
        $this->moveToQueue('QueueCartDisconnect');

        return 'Disconnect requested for '.$in['date'].'.';
    }

    public function reconnect(array $in): string
    {
        $this->ercot('650_01', 'Reconnect', today()->toDateString());
        $this->c->update(['status' => 'Good - On Flow']);
        $this->moveToQueue('QueueCartReconnectSent');

        return 'Reconnect requested.';
    }

    public function moveOut(array $in): string
    {
        $this->ercot('814_24', 'Move Out', $in['date']);
        ServiceAddress::where('customer_id', $this->c->id)->whereNull('service_end')->update(['service_end' => $in['date']]);
        PlanTerm::where('customer_id', $this->c->id)->where('status', 'current')->update(['contract_end' => $in['date']]);
        $this->c->update(['status' => 'Moved Out']);
        $this->moveToQueue('QueueCartMvo');

        return 'Move out scheduled for '.$in['date'].'.';
    }

    public function cancelOrder(array $in): string
    {
        if ($this->c->ercotTransactions()->exists()) {
            $this->ercot('814_08', 'Cancel');
        }
        $this->c->update(['status' => 'Cancelled']);
        CustomerFlag::create(['customer_id' => $this->c->id, 'flag' => 'Order has been cancelled manually', 'user_id' => $this->user?->id, 'author' => $this->who()]);
        $this->moveToQueue('QueueCartCancel', $in['reason']);

        return 'Order cancelled. Reason: '.$in['reason'];
    }

    public function deleteOrder(array $in): array
    {
        Gate::authorize('delete');
        $account = $this->c->account;
        $this->c->delete();

        return ['Account '.$account.' deleted.', route('corral.customers.index')];
    }

    public function winback(array $in): string
    {
        $this->moveToQueue('QueueCartWinback');

        return 'Added to the winback queue. '.$this->contact('Email', 'Winback Offer');
    }

    public function clearExceptions(array $in): string
    {
        $was = $this->c->exception;
        $this->c->update(['exception' => null]);
        QueueLog::where('customer_id', $this->c->id)->whereNull('exited_at')->where('queue', 'like', 'QueueCartException%')->update(['exited_at' => now()]);
        CustomerFlag::where('customer_id', $this->c->id)->whereNull('removed_at')->where('flag', 'like', 'Exception:%')->update(['removed_at' => now()]);

        return $was ? 'Cleared exception: '.$was.'.' : 'No exceptions to clear.';
    }

    public function repairPlan(array $in): string
    {
        $terms = PlanTerm::where('customer_id', $this->c->id)->orderBy('contract_start')->get();
        $current = $terms->filter(fn ($t) => $t->contract_start && $t->contract_start->lte(today()))->last();
        foreach ($terms as $t) {
            $status = $current && $t->is($current) ? 'current' : ($t->contract_start && $t->contract_start->lte(today()) ? 'ended' : 'not started');
            if ($t->status !== $status) {
                $t->update(['status' => $status]);
            }
        }
        if ($current && $current->plan_id && $current->plan_id !== $this->c->plan_id) {
            $this->c->update(['plan_id' => $current->plan_id, 'rate_class' => $current->rate_class]);
        }

        return $current ? 'Current plan set to '.$current->rate_class.'.' : 'No plan term has started yet.';
    }

    public function undoLastPlan(array $in): string
    {
        $last = PlanTerm::where('customer_id', $this->c->id)->where('status', 'not started')->orderByDesc('ordered_at')->orderByDesc('id')->first();
        if (! $last) {
            return 'No pending plan to undo.';
        }
        $last->delete();

        return 'Removed pending plan '.$last->rate_class.'.';
    }

    public function addOrder(array $in): array
    {
        return ['Opened a new order for this premise.', route('corral.orders.create', ['esiid' => $this->c->esiid])];
    }

    // ---------- Customer Service ----------

    public function makePayment(array $in): string
    {
        $ok = $this->configured('stripe');
        Payment::create(['customer_id' => $this->c->id, 'reference' => 'PAY-'.strtoupper(Str::random(8)), 'paid_on' => today(), 'paid_time' => now()->format('H:i:s'),
            'amount' => $in['amount'], 'method' => 'Card', 'source' => $this->source, 'kind' => 'Balance Payment', 'payment_method_id' => $in['method'],
            'status' => $ok ? 'Failed' : 'Pending']);
        ApiLog::create(['customer_id' => $this->c->id, 'api' => 'Stripe', 'action' => 'charges.create', 'status' => $ok ? 'not built' : 'skipped', 'created_at' => now(),
            'request' => ['body' => ['amount' => (int) round($in['amount'] * 100), 'currency' => 'usd', 'description' => 'Payment for '.$this->c->account, 'source' => '•••• hidden']], 'response' => ['status' => $ok ? 'not built' : 'skipped', 'body' => ['message' => $ok ? 'The charge client has not been built yet: nothing was sent.' : 'Stripe is not configured: nothing was sent.']]]);

        return $ok
            ? 'Stripe credentials are set, but the charge client has not been built yet. Payment marked Failed.'
            : 'Payment of $'.number_format($in['amount'], 2).' recorded as Pending. The card was not charged: Stripe is not configured.';
    }

    public function addBillCredit(array $in): string
    {
        LedgerEntry::create(['customer_id' => $this->c->id, 'kind' => 'credit', 'amount' => $in['amount'], 'description' => $in['description'], 'user_id' => $this->user?->id]);

        return 'Pending bill credit of $'.number_format($in['amount'], 2).' added.';
    }

    public function balanceAdjustment(array $in): string
    {
        Payment::create(['customer_id' => $this->c->id, 'reference' => 'ADJ-'.strtoupper(Str::random(8)), 'paid_on' => today(), 'paid_time' => now()->format('H:i:s'),
            'amount' => $in['amount'], 'method' => 'Adjustment', 'source' => 'Corral', 'kind' => 'Balance Adjustment', 'status' => 'Success']);
        $this->c->decrement('balance', $in['amount']);

        return 'Balance adjusted by -$'.number_format($in['amount'], 2).'. Reason: '.$in['description'];
    }

    public function paymentArrangement(array $in): string
    {
        $this->addProduct('Payment Arrangement', ['installments' => (int) $in['installments'], 'first_due' => $in['date']]);
        CustomerFlag::create(['customer_id' => $this->c->id, 'flag' => 'VIP - Payment Arrangement', 'user_id' => $this->user?->id, 'author' => $this->who()]);
        $this->enterQueue('QueueCartVipPaymentarrangement');

        return 'Payment arrangement: '.$in['installments'].' installments starting '.$in['date'].'.';
    }

    public function deferredPaymentPlan(array $in): string
    {
        $this->addProduct('Deferred Payment Plan', ['installments' => (int) $in['installments'], 'first_due' => $in['date']]);
        CustomerFlag::create(['customer_id' => $this->c->id, 'flag' => 'VIP - Deferred Payment Plan', 'user_id' => $this->user?->id, 'author' => $this->who()]);
        $this->enterQueue('QueueCartVipDeferredpayment');

        return 'Deferred payment plan: '.$in['installments'].' installments starting '.$in['date'].'.';
    }

    public function transferPayments(array $in): string
    {
        $other = $this->findAccount($in['account']);
        $payment = Payment::where('customer_id', $this->c->id)->findOrFail($in['payment']);
        $payment->update(['customer_id' => $other->id]);
        $other->notes()->create(['user_id' => $this->user?->id, 'author' => $this->who(), 'body' => 'Payment '.$payment->reference.' transferred in from account '.$this->c->account.'.']);

        return 'Payment '.$payment->reference.' moved to account '.$other->account.'.';
    }

    public function transferStars(array $in): string
    {
        $other = $this->findAccount($in['account']);
        $stars = (int) $in['stars'];
        abort_if($stars > $this->c->stars, 422, 'This account only has '.$this->c->stars.' stars.');
        StarEntry::create(['customer_id' => $this->c->id, 'reason' => 'Transferred to '.$other->account, 'stars' => -$stars, 'user_id' => $this->user?->id]);
        StarEntry::create(['customer_id' => $other->id, 'reason' => 'Transferred from '.$this->c->account, 'stars' => $stars, 'user_id' => $this->user?->id]);
        $this->c->syncStars();
        $other->syncStars();

        return $stars.' stars transferred to account '.$other->account.'.';
    }

    public function balancedBilling(array $in): string
    {
        $this->addProduct('Balanced Billing');
        $this->enterQueue('QueueCartBalancedBilling');

        return 'Balanced billing set up.';
    }

    public function transferService(array $in): string
    {
        $old = $this->c->esiid;
        $moveIn = Carbon::parse($in['date']);
        ServiceAddress::where('customer_id', $this->c->id)->whereNull('service_end')->update(['service_end' => $moveIn->copy()->subDay()]);
        ServiceAddress::create(['customer_id' => $this->c->id, 'esiid' => $in['esiid'], 'street' => $in['street'], 'city' => $in['city'], 'zip' => $in['zip'], 'ordered_at' => now(), 'service_start' => $moveIn]);
        $this->ercot('814_24', 'Move Out', $moveIn->copy()->subDay()->toDateString(), 'request', $old);
        $this->ercot('814_16', 'MoveIn', $moveIn->toDateString(), 'request', $in['esiid']);
        $this->c->update(['address' => $in['street'], 'city' => $in['city'], 'zip' => $in['zip'], 'esiid' => $in['esiid']]);
        $this->moveToQueue('QueueCartTransition');

        return 'Service transferring to '.$in['street'].', '.$in['city'].' on '.$moveIn->toDateString().'.';
    }

    public function changePlan(array $in): array
    {
        return ['Opened Renew / Change Plan.', route('corral.orders.create', ['renew' => $this->c->account])];
    }

    public function spendStars(array $in): array
    {
        return ['Opened Spend Stars.', route('corral.customers.stars', $this->c)];
    }

    public function resetLogin(array $in): string
    {
        return 'MyAccount login reset requested. '.$this->contact('Email', 'MyAccount - Password Reset');
    }

    public function addAuthorizedUser(array $in): string
    {
        $users = $this->c->authorized_users ?? [];
        $users[] = ['name' => $in['name'], 'phone' => $in['phone'], 'added' => today()->toDateString()];
        $this->c->update(['authorized_users' => $users]);

        return $in['name'].' added as an authorized user.';
    }

    public function sendEmail(array $in): string
    {
        return $this->contact('Email', $in['template'], $in['message']);
    }

    public function sendText(array $in): string
    {
        return $this->contact('SMS', 'Agent text', $in['message']);
    }

    public function testMeter(array $in): string
    {
        $this->ercot('650_01', 'Meter Test Request');
        $this->enterQueue('QueueCartMeterTest');

        return 'Meter test requested.';
    }

    public function reread(array $in): string
    {
        $this->ercot('650_01', 'Meter Re-Read Request');
        $this->enterQueue('QueueCartMeterRead');

        return 'Meter re-read requested.';
    }

    public function requestUsage(array $in): string
    {
        $this->ercot('867_03', 'Usage Request');
        $this->enterQueue('QueueCartUsagerequest');

        return 'Usage requested from the utility.';
    }
}
