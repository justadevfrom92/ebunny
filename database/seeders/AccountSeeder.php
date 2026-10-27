<?php

namespace Database\Seeders;

use App\Models\ApiLog;
use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\CustomerFile;
use App\Models\CustomerFlag;
use App\Models\CustomerProduct;
use App\Models\ErcotTransaction;
use App\Models\LedgerEntry;
use App\Models\PaymentMethod;
use App\Models\Phonecall;
use App\Models\Plan;
use App\Models\PlanTerm;
use App\Models\QueueLog;
use App\Models\ServiceAddress;
use App\Models\StarEntry;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Fills every FICTIONAL sample account with the details the Corral account page
 * shows: contact and meter info, flags, products, payment methods, plan and
 * address logs, ERCOT transactions, queue history, files, emails, stars, API
 * calls and phone calls.
 */
class AccountSeeder extends Seeder
{
    private const ZONES = [
        'TX-E-CENTERPOINT' => ['HOUSTON', 'COAST', 'CPT'], 'TX-E-ONCOR' => ['NORTH', 'NCENT', 'ONC'],
        'TX-E-AEPCENTRAL' => ['SOUTH', 'SCENT', 'ACE'], 'TX-E-AEPNORTH' => ['WEST', 'WEST', 'ANO'], 'TX-E-TNMP' => ['HOUSTON', 'COAST', 'TNM'],
    ];

    public function run(): void
    {
        mt_srand(9001);
        $csr = User::where('email', 'csr@example.com')->first();
        $rolloffs = Plan::whereIn('internal', ['JEY3', 'JEY2', 'JEY'])->get()->keyBy('internal');

        foreach (Customer::with(['plan', 'market', 'payments', 'bills', 'notes'])->get() as $c) {
            $this->account($c, $csr, $rolloffs);
        }

        // Every account gets a referral code; the first account has a sample My Account login
        foreach (Customer::whereNull('referral_code')->get() as $c) {
            $c->forceFill(['referral_code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $c->first_name), 0, 4)).substr($c->account, -4)])->saveQuietly();
        }
        Customer::orderBy('id')->first()?->forceFill(['username' => 'demo.customer', 'password' => 'password'])->saveQuietly();

        // A few PDFs were regenerated after an address or plan change
        foreach (CustomerFile::whereNull('path')->whereRaw('id % 9 = 0')->get() as $f) {
            $f->forceFill(['generated_at' => $f->created_at->copy()->addDays(2), 'regenerated' => 1 + $f->id % 2])->saveQuietly();
        }
    }

    private function account(Customer $c, ?User $csr, $rolloffs): void
    {
        $created = Carbon::parse($c->created_at);
        $start = $created->copy()->addDays(5)->startOfDay();
        $biz = $c->type !== 'Residential';
        [$zone, $profileZone, $mcode] = self::ZONES[$c->market?->name] ?? ['NORTH', 'NCENT', 'ONC'];
        $parts = explode(' ', $c->name);
        $rejected = str_starts_with($c->status, 'Rejected');
        $flowing = $c->status === 'Good - On Flow' || str_starts_with($c->status, 'Dropped');
        $plan = $c->plan;
        $rateClass = $plan ? $created->format('Ymd').'_'.($plan->term === 1 ? 'V' : 'F').'_'.$mcode.'_'.$plan->internal : null;

        $c->forceFill([
            'first_name' => $parts[0], 'last_name' => $parts[1] ?? '',
            'phone_type' => ['mobile', 'mobile', 'landline', 'voip'][mt_rand(0, 3)],
            'username' => strtolower(substr($parts[0], 0, 1).($parts[1] ?? 'user')).mt_rand(10, 99),
            'tec_score' => mt_rand(540, 820), 'ssn_last4' => str_pad((string) mt_rand(0, 9999), 4, '0', STR_PAD_LEFT),
            'ip' => '203.0.113.'.mt_rand(2, 254), 'ip_location' => 'United States, Texas',
            'meter_number' => 'M'.mt_rand(10000000, 99999999),
            'load_profile' => ($biz ? 'BUSMEDLF_' : 'RESLOWR_').$profileZone.'_IDR_WS_NOTOU', 'load_zone' => $zone,
            'meter_type' => mt_rand(0, 3) ? 'AMSR' : 'AMSM',
            'billing_street' => $c->address, 'billing_city' => $c->city, 'billing_state' => 'TX', 'billing_zip' => $c->zip,
            'move_switch' => mt_rand(0, 2) ? 'switch' : 'move',
            'requested_start' => $start, 'actual_start' => $flowing ? $start : null,
            'status_info' => $flowing ? (mt_rand(0, 3) ? 'Active' : 'Renewed') : ($rejected ? 'Rejected' : 'New'),
            'rate_class' => $rateClass,
            'channel' => ['1 - Organic (default)', '2 - Paid Search', '3 - Broker', '4 - Referral'][mt_rand(0, 3)],
            'msid' => ['1001 - Organic', '35001 - Paid Search', '52001 - Partner', '75001 - Referral'][mt_rand(0, 3)],
            'promo_code' => mt_rand(0, 2) ? null : ['SAVE25', 'TXFIRST', 'MOVE50'][mt_rand(0, 2)],
            'marketing_opt_in' => (bool) mt_rand(0, 4),
            'authorized_users' => mt_rand(0, 5) ? [] : [['name' => 'Jamie '.($parts[1] ?? 'Sample'), 'phone' => '(555) 555-01'.mt_rand(10, 99), 'added' => $created->copy()->addMonth()->toDateString()]],
            'linked_accounts' => [],
        ])->saveQuietly();

        // Flags
        if (! mt_rand(0, 3)) {
            $flag = ['SOLAR CUSTOMER', 'VIP - Friends & Family', 'VIP - Payment Arrangement', 'Returned Mail', 'VIP - Critical Care'][mt_rand(0, 4)];
            CustomerFlag::create(['customer_id' => $c->id, 'flag' => $flag, 'user_id' => $csr?->id, 'author' => $csr?->name, 'created_at' => $created->copy()->addDays(mt_rand(10, 60))]);
        }
        if ($c->exception) {
            CustomerFlag::create(['customer_id' => $c->id, 'flag' => 'Exception: '.$c->exception, 'author' => 'System', 'created_at' => $created]);
        }

        // Products
        $products = ['Rangler Rewards'];
        if ($c->autopay) {
            $products[] = 'AutoPay';
        }
        if ($c->paperless) {
            $products[] = 'Paperless Billing';
        }
        if ($c->peak_perks) {
            $products[] = 'Peak Perks';
        }
        foreach (['Giddyup Guarantee', 'Freedom Flex', 'Air Conditioning Protection Program', 'Surge Protection Program', 'Solar Buyback', 'Pick Your Due Date'] as $extra) {
            if (! mt_rand(0, 5)) {
                $products[] = $extra;
            }
        }
        foreach ($products as $i => $p) {
            CustomerProduct::create(['customer_id' => $c->id, 'product' => $p, 'created_at' => $created->copy()->addSeconds(10 + $i)->addDays($i > 3 ? mt_rand(5, 90) : 0)]);
        }

        // Payment methods
        $card = PaymentMethod::create(['customer_id' => $c->id, 'type' => 'Credit Card', 'last4' => ['4242', '1111', '0005', '4444'][mt_rand(0, 3)],
            'expires' => sprintf('%02d/%d', mt_rand(1, 12), mt_rand(2027, 2031)), 'nickname' => 'My Card', 'vendor' => 'Stripe', 'autopay' => $c->autopay, 'created_at' => $created]);
        if (! mt_rand(0, 3)) {
            PaymentMethod::create(['customer_id' => $c->id, 'type' => 'Bank Account', 'last4' => (string) mt_rand(1000, 9999), 'nickname' => 'Checking', 'vendor' => 'Stripe', 'created_at' => $created->copy()->addMonths(2)]);
        }

        // Pending credits/debits
        if (! mt_rand(0, 4)) {
            LedgerEntry::create(['customer_id' => $c->id, 'kind' => 'credit', 'amount' => 25, 'description' => 'Refer-A-Friend credit', 'created_at' => now()->subDays(mt_rand(1, 20))]);
        }
        if (! mt_rand(0, 6)) {
            LedgerEntry::create(['customer_id' => $c->id, 'kind' => 'debit', 'amount' => 174.99, 'description' => 'ecobee', 'created_at' => now()->subDays(mt_rand(1, 20))]);
        }

        // Plan change log: current term plus the rolloff chain
        if ($plan) {
            $end = $plan->term > 1 ? $start->copy()->addMonths($plan->term)->subDay() : null;
            PlanTerm::create(['customer_id' => $c->id, 'plan_id' => $plan->id, 'rate_class' => $rateClass, 'energy_charge' => round(mt_rand(8000, 11000) / 100000, 8),
                'rate_2000' => round(mt_rand(1250, 1550) / 100, 3), 'ordered_at' => $created, 'contract_start' => $flowing ? $start : null,
                'contract_end' => $flowing ? $end : null, 'status' => $flowing ? 'current' : 'not started']);
            if ($flowing && $end) {
                $from = $end->copy()->addDay();
                foreach (['JEY3', 'JEY2', 'JEY'] as $code) {
                    $r = $rolloffs[$code] ?? null;
                    PlanTerm::create(['customer_id' => $c->id, 'plan_id' => $r?->id, 'rate_class' => $created->format('Ymd').'_V_'.$mcode.'_'.$code,
                        'energy_charge' => 0.154544, 'rate_2000' => 19.7, 'ordered_at' => $created, 'contract_start' => $from, 'contract_end' => $from->copy()->addMonth()->subDay(), 'status' => 'not started']);
                    $from = $from->copy()->addMonth();
                }
            }
        }

        // Address change log
        ServiceAddress::create(['customer_id' => $c->id, 'esiid' => $c->esiid, 'street' => $c->address, 'city' => $c->city, 'zip' => $c->zip,
            'ordered_at' => $created, 'service_start' => $flowing ? $start : null]);

        // ERCOT transactions
        $label = $c->move_switch === 'move' ? 'MoveIn' : 'Switch';
        ErcotTransaction::create(['customer_id' => $c->id, 'esiid' => $c->esiid, 'trans_date' => $created->copy()->addDay(), 'purpose' => 'request',
            'scheduled_on' => $start, 'trans_type' => $c->move_switch === 'move' ? '814_16' : '814_01', 'label' => $label, 'created_at' => $created->copy()->addDay()->setTime(15, 56)]);
        if (! in_array($c->status, ['Submitted', 'Pending - Credit', 'Pending - Utility Not Answered'], true)) {
            ErcotTransaction::create(['customer_id' => $c->id, 'esiid' => $c->esiid, 'trans_date' => $created->copy()->addDay(), 'purpose' => 'response',
                'scheduled_on' => $start, 'trans_type' => $c->move_switch === 'move' ? '814_05' : '814_04', 'label' => $label.($rejected ? ' Reject' : ' Response'),
                'tracking' => (string) mt_rand(100000000, 999999999).$created->format('YmdHis'), 'created_at' => $created->copy()->addDay()->setTime(16, 53)]);
        }
        if ($flowing) {
            ErcotTransaction::create(['customer_id' => $c->id, 'esiid' => $c->esiid, 'trans_date' => $start, 'purpose' => 'response', 'scheduled_on' => $start,
                'trans_type' => '867_04', 'label' => 'Initial Meter Read', 'created_at' => $start->copy()->setTime(6, 0)]);
        }

        // Queue log: the path the order took, ending in its current queue
        $path = ['QueueCartStart', 'QueueCartActive', 'QueueCartSubmit', 'QueueCartFulfilledPending'];
        $path = array_merge($path, match (true) {
            $c->status === 'Good - On Flow' => ['QueueCartFulfilledRequested', 'QueueCartFulfilledAccepted', 'QueueCartFulfilledFlowing'],
            str_starts_with($c->status, 'Dropped') => ['QueueCartFulfilledRequested', 'QueueCartFulfilledAccepted', 'QueueCartFulfilledFlowing', 'QueueCartFulfilledChurned'],
            $rejected => ['QueueCartFulfilledRequested', 'QueueCartFulfilledRejected'],
            $c->status === 'Pending - Deposit Due' => ['QueueCartFulfilledDeposit'],
            $c->status === 'Pending - Utility Not Answered' => ['QueueCartFulfilledRequested'],
            default => [],
        });
        $t = $created->copy();
        foreach ($path as $i => $q) {
            $out = $i < count($path) - 1 ? $t->copy()->addMinutes(mt_rand(1, 600)) : null;
            QueueLog::create(['customer_id' => $c->id, 'queue' => $q, 'entered_at' => $t, 'exited_at' => $out]);
            $t = $out ?? $t;
        }
        if ($c->exception) {
            $q = ['No ESIID' => 'QueueCartExceptionEsiid', 'Switch Hold' => 'QueueCartExceptionSwitchHold', 'Possible Duplicate' => 'QueueCartExceptionDuplicate'][$c->exception] ?? 'QueueCartException';
            QueueLog::create(['customer_id' => $c->id, 'queue' => $q, 'entered_at' => $created->copy()->addMinutes(2)]);
        }

        // Files
        foreach ([['Electricity Facts Label', 'efl'], ['Your Rights as a Customer', 'yrac'], ['Terms of Service', 'tos']] as [$name, $kind]) {
            CustomerFile::create(['customer_id' => $c->id, 'name' => $name, 'kind' => $kind, 'created_at' => $created]);
        }
        if ($rateClass) {
            CustomerFile::create(['customer_id' => $c->id, 'name' => $rateClass.'.pdf', 'kind' => 'efl', 'created_at' => $created->copy()->addMinutes(2)]);
        }
        if (! $rejected) {
            CustomerFile::create(['customer_id' => $c->id, 'name' => 'welcome-packet-'.$c->ticket.'.pdf', 'kind' => 'welcome', 'created_at' => $created->copy()->addDays(3)]);
        }

        // Bills: periods, due dates, invoice numbers and what was paid
        $balance = 0.0;
        $payments = $c->payments->where('status', 'Success')->sortBy('paid_on')->values();
        foreach ($c->bills->sortBy('billed_on')->values() as $i => $b) {
            $billed = Carbon::parse($b->billed_on);
            $pay = $payments[$i] ?? null;
            $paid = $pay ? min($pay->amount, $b->amount) : 0;
            $balance = round($balance + $b->amount - $paid, 2);
            $b->forceFill([
                'period_start' => $billed->copy()->subMonth(), 'period_end' => $billed->copy()->subDay(), 'due_on' => $billed->copy()->addDays(16),
                'paid_on' => $pay?->paid_on, 'amount_paid' => $paid, 'balance_after' => $balance,
                'invoice' => strtoupper($billed->format('M')).mt_rand(1000000, 9999999), 'ontime' => ! $pay || Carbon::parse($pay->paid_on)->lte($billed->copy()->addDays(16)),
            ])->saveQuietly();
            CustomerFile::create(['customer_id' => $c->id, 'name' => 'invoice-'.substr(md5($b->reference), 0, 24).'.pdf', 'kind' => 'invoice', 'created_at' => $billed]);
            ContactLog::create(['customer_id' => $c->id, 'channel' => 'Email', 'template' => 'Bill Ready', 'sent_at' => $billed->copy()->addMinutes(5),
                'opened_at' => mt_rand(0, 2) ? $billed->copy()->addHours(mt_rand(1, 40)) : null, 'created_at' => $billed->copy()->addMinutes(5)]);
            ApiLog::create(['customer_id' => $c->id, 'api' => 'Utilibill', 'action' => 'bill.import '.$b->reference, 'status' => '200', 'response_ms' => mt_rand(120, 900), 'created_at' => $billed]);
        }
        $c->forceFill(['due_date' => $c->bills->max('billed_on') ? Carbon::parse($c->bills->max('billed_on'))->addDays(16) : null])->saveQuietly();

        // Payments: time, type, card and confirmation number
        foreach ($c->payments as $p) {
            $p->forceFill(['paid_time' => sprintf('%02d:%02d:%02d', mt_rand(7, 21), mt_rand(0, 59), mt_rand(0, 59)),
                'kind' => $p->source === 'AutoPay' ? 'AutoPay' : 'Balance Payment', 'payment_method_id' => $card->id,
                'confirmation' => $p->status === 'Success' ? (string) mt_rand(5009000000, 5009999999) : null])->saveQuietly();
            if ($p->status === 'Success') {
                ContactLog::create(['customer_id' => $c->id, 'channel' => 'Email', 'template' => 'Billing - Resi - Payment Made', 'sent_at' => Carbon::parse($p->paid_on)->setTime(12, 0), 'created_at' => Carbon::parse($p->paid_on)->setTime(12, 0)]);
                StarEntry::create(['customer_id' => $c->id, 'reason' => 'Bill Payment', 'stars' => round($p->amount * 0.05, 1), 'created_at' => Carbon::parse($p->paid_on)->setTime(12, 1)]);
            }
            ApiLog::create(['customer_id' => $c->id, 'api' => 'Stripe', 'action' => 'charges.create', 'status' => $p->status === 'Success' ? '200' : '402', 'response_ms' => mt_rand(300, 1400), 'created_at' => Carbon::parse($p->paid_on)->setTime(12, 0)]);
        }

        // Emails, stars, credit check, phone calls
        ContactLog::create(['customer_id' => $c->id, 'channel' => 'Email', 'template' => 'Welcome Letter', 'sent_at' => $created->copy()->addMinutes(1), 'opened_at' => $created->copy()->addHours(2), 'clicked_at' => mt_rand(0, 1) ? $created->copy()->addHours(2)->addMinute() : null, 'created_at' => $created->copy()->addMinutes(1)]);
        if ($c->paperless) {
            StarEntry::create(['customer_id' => $c->id, 'reason' => 'Paperless Signup', 'stars' => 10, 'created_at' => $created->copy()->addSeconds(30)]);
        }
        ApiLog::create(['customer_id' => $c->id, 'api' => 'Experian', 'action' => 'credit.check', 'status' => '200', 'response_ms' => mt_rand(800, 2500), 'created_at' => $created->copy()->addMinutes(2)]);
        for ($i = 0, $n = mt_rand(0, 3); $i < $n; $i++) {
            Phonecall::create(['customer_id' => $c->id, 'user_id' => $csr?->id, 'agent_id' => 'A'.mt_rand(100, 140), 'phone' => $c->phone,
                'direction' => mt_rand(0, 3) ? 'inbound' : 'outbound', 'started_at' => $created->copy()->addDays(mt_rand(1, 200))->setTime(mt_rand(8, 17), mt_rand(0, 59))->min(now()->subDays(mt_rand(0, 20))->setTime(mt_rand(8, 17), mt_rand(0, 59))),
                'duration_sec' => mt_rand(45, 1500), 'disposition' => $issue = ['Status Inquiry', 'Balance Inquiry', 'Payment Made', 'Change Plan', 'Outage'][mt_rand(0, 4)],
                'transcript' => self::transcript($issue, $c->first_name ?: 'there', $csr?->name ?? 'the agent')]);
        }
        // Text messages for customers with a mobile phone: a bill reminder, sometimes a reply and an answer
        if ($c->phone_type === 'mobile' && $flowing) {
            $at = $created->copy()->addDays(mt_rand(20, 90))->setTime(mt_rand(9, 18), mt_rand(0, 59))->min(now()->subHours(3));
            ContactLog::create(['customer_id' => $c->id, 'channel' => 'SMS', 'direction' => 'out', 'phone' => $c->phone, 'template' => 'Bill Ready',
                'body' => 'Your '.config('brand.name').' bill is ready. Reply HELP for help or STOP to opt out.', 'sent_at' => $at, 'created_at' => $at]);
            if (mt_rand(0, 2) === 0) {
                $reply = $at->copy()->addMinutes(mt_rand(3, 90));
                ContactLog::create(['customer_id' => $c->id, 'channel' => 'SMS', 'direction' => 'in', 'phone' => $c->phone, 'template' => 'Reply',
                    'body' => ['Can I set up autopay by text?', 'When is my bill due?', 'Thanks!', 'Is there an outage in my area?'][mt_rand(0, 3)], 'status' => 'received', 'sent_at' => $reply, 'created_at' => $reply]);
            }
        }
        foreach ($c->notes as $note) {
            if ($note->author !== 'System') {
                $note->forceFill(['category' => 'Order', 'action' => 'Status Inquiry', 'priority' => 'Low'])->saveQuietly();
            }
        }
        $c->syncStars();
    }

    /** A short fictional call transcript for the sample data. */
    private static function transcript(string $issue, string $first, string $agent): string
    {
        $agentFirst = strtok($agent, ' ');
        $lines = [
            "Agent: Thanks for calling, this is {$agentFirst}. Who do I have the pleasure of speaking with?",
            "Customer: Hi, this is {$first}.",
        ];
        $lines = array_merge($lines, match ($issue) {
            'Balance Inquiry' => ['Customer: I wanted to check what I owe this month.', "Agent: Your current balance is on the last bill we sent; it's due on the date shown. Want me to text you a payment link?", 'Customer: Yes please.'],
            'Payment Made' => ["Customer: I'd like to make a payment.", 'Agent: I can take that now. Card or bank account?', 'Customer: Card.', "Agent: Done, you'll get a confirmation email shortly."],
            'Change Plan' => ['Customer: My contract is ending and I want to look at other plans.', "Agent: Let me pull up what's available at your address. The 24-month fixed plan would lower your rate.", "Customer: Let's do that one."],
            'Outage' => ['Customer: My power is out.', "Agent: I'm sorry about that. Outages are handled by your utility; I'll give you their outage number and report it.", 'Customer: Thanks.'],
            default => ["Customer: I'm checking on the status of my service.", "Agent: Your enrollment went through and service is active. You'll get your first bill after the first meter read.", 'Customer: Great, thanks.'],
        }, ['Agent: Is there anything else I can help with today?', 'Customer: No, that was it.', 'Agent: Have a great day.']);

        return implode("\n", $lines);
    }
}
