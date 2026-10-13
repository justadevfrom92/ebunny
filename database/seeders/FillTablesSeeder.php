<?php

namespace Database\Seeders;

use App\Models\ApiLog;
use App\Models\Bill;
use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\HistoryItem;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Phonecall;
use App\Models\ReferenceRow;
use App\Models\Refund;
use App\Models\ReportRun;
use App\Models\RewardOffer;
use App\Models\StarEntry;
use App\Models\User;
use App\Reports\Report;
use Illuminate\Database\Seeder;

/**
 * FICTIONAL sample activity so every admin table has rows: decided credits/debits,
 * refunds at each step, deposits, reward redemptions and gift cards, past drawing
 * winners, API calls for every integration, Corral report runs, and texts, calls
 * and bills on every account that should have them. Runs after the account seeders.
 */
class FillTablesSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(9090);
        $this->bills();
        $this->ledger();
        $this->refundsAndDeposits();
        $this->rewards();
        $this->drawing();
        $this->apiCalls();
        $this->reportRuns();
        $this->textsAndCalls();
        // Paperless sign-up stars for accounts that have none yet (new enrollments)
        foreach (Customer::doesntHave('starEntries')->get() as $c) {
            $c->update(['paperless' => true]);
            StarEntry::create(['customer_id' => $c->id, 'reason' => 'Paperless Signup', 'stars' => 5, 'created_at' => $c->created_at, 'updated_at' => $c->created_at]);
            $c->syncStars();
        }
    }

    private function uid(string $email): ?int
    {
        return User::where('email', $email)->value('id');
    }

    /** Accounts that were on flow but have no bills yet get their months of bills, payments and usage. */
    private function bills(): void
    {
        foreach (Customer::doesntHave('bills')->whereIn('status', ['Good - On Flow', 'Dropped - Churned'])->get() as $c) {
            $months = $c->status === 'Dropped - Churned' ? 5 : 2;
            $start = today()->subMonths($months + ($c->status === 'Dropped - Churned' ? 3 : 0))->startOfMonth()->addDays(mt_rand(0, 20));
            $balance = 0.0;
            for ($i = 0; $i < $months; $i++) {
                $from = $start->copy()->addMonths($i);
                $billed = $from->copy()->addMonth();
                $kwh = mt_rand(700, 1900);
                $amount = round($kwh * 0.142 + 4.95, 2);
                $paid = round($amount, 2);
                $balance = round($balance + $amount - $paid, 2);
                Bill::create(['customer_id' => $c->id, 'reference' => 'BILL-'.str_pad((string) mt_rand(60000, 99999), 5, '0', STR_PAD_LEFT), 'billed_on' => $billed, 'kwh' => $kwh,
                    'amount' => $amount, 'bill_type' => 'original', 'period_start' => $from, 'period_end' => $billed->copy()->subDay(), 'due_on' => $billed->copy()->addDays(16),
                    'paid_on' => $billed->copy()->addDays(9), 'amount_paid' => $paid, 'balance_after' => $balance, 'invoice' => strtoupper($billed->format('M')).mt_rand(1000000, 9999999), 'ontime' => true]);
                Payment::create(['customer_id' => $c->id, 'reference' => 'PAY-'.mt_rand(70000, 99999), 'paid_on' => $billed->copy()->addDays(9), 'paid_time' => sprintf('%02d:%02d:%02d', mt_rand(7, 20), mt_rand(0, 59), mt_rand(0, 59)),
                    'amount' => $paid, 'method' => $i % 2 ? 'Card' : 'ACH', 'source' => 'MyAccount', 'kind' => 'One-Time', 'status' => 'Success', 'confirmation' => (string) mt_rand(5000000000, 5999999999)]);
                StarEntry::create(['customer_id' => $c->id, 'reason' => 'Bill Payment', 'stars' => round($paid / 20, 1), 'created_at' => $billed->copy()->addDays(9)]);
            }
            $c->syncStars();
        }
    }

    /** Caboose → Credits & Debits: entries finance already applied or rejected. */
    private function ledger(): void
    {
        $deciders = array_values(array_filter([$this->uid('finance@example.com'), User::where('email', 'like', 'hayden.sumwell@%')->value('id'), User::where('email', 'like', 'avery.ledgerly@%')->value('id')]));
        $adders = [$this->uid('csr@example.com'), $this->uid('lead@example.com'), null];
        $kinds = [['credit', 'Outage goodwill credit', 15], ['credit', 'Billing correction: estimated read', 38.4], ['credit', '$25 Bill Credit', 25], ['credit', 'Referral credit', 50],
            ['credit', 'Late fee waived', 10], ['debit', 'Returned payment fee', 25], ['debit', 'Meter re-read fee', 18.5], ['debit', 'Smart thermostat', 174.99], ['credit', '$10 Bill Credit', 10], ['debit', 'Disconnect/reconnect fee', 35]];
        $customers = Customer::whereIn('status', ['Good - On Flow', 'Dropped - Churned'])->get();
        for ($i = 0; $i < 26; $i++) {
            [$kind, $desc, $amt] = $kinds[$i % count($kinds)];
            $c = $customers[$i % $customers->count()];
            $added = now()->subDays(mt_rand(3, 75))->setTime(mt_rand(8, 17), mt_rand(0, 59));
            $applied = $i % 6 !== 5;
            $decided = $added->copy()->addHours(mt_rand(3, 60));
            $e = LedgerEntry::create(['customer_id' => $c->id, 'kind' => $kind, 'amount' => $amt, 'description' => $desc, 'user_id' => $adders[$i % 3],
                'status' => $applied ? 'applied' : 'deleted', 'decided_by' => $deciders[$i % count($deciders)], 'decided_at' => $decided,
                'decision_note' => $applied ? ($i % 3 ? null : 'Matches the account notes') : 'Duplicate of an earlier credit']);
            $e->forceFill(['created_at' => $added, 'updated_at' => $decided])->saveQuietly();
            $c->notes()->create(['user_id' => $e->decided_by, 'author' => User::find($e->decided_by)?->name ?? 'Finance', 'category' => 'Payment', 'action' => 'Fees', 'priority' => 'Low',
                'body' => ucfirst($kind).' of $'.number_format($amt, 2).' ('.$desc.') '.($applied ? 'applied' : 'rejected').' in Caboose.', 'created_at' => $decided, 'updated_at' => $decided]);
        }
    }

    /** Caboose → Refunds (every step) and Deposits (held). */
    private function refundsAndDeposits(): void
    {
        $finance = $this->uid('finance@example.com');
        $requesters = [$this->uid('csr@example.com'), $this->uid('lead@example.com'), $finance];
        // Closed accounts with a credit left after the final bill
        $closed = Customer::whereIn('status', ['Dropped - Churned', 'Rejected - By Utility'])->get();
        $credits = [42.18, 86.4, 23.75, 112.9, 64.1, 19.6, 151.25, 37.8];
        foreach ($closed as $i => $c) {
            if ($c->balance >= 0) {
                $c->update(['balance' => -$credits[$i % count($credits)]]);
            }
        }
        // Deposits held on accounts in good standing
        foreach (Customer::where('status', 'Good - On Flow')->orderBy('id')->limit(9)->get() as $i => $c) {
            $c->update(['deposit_held' => [150, 200, 125, 250, 100, 175, 150, 300, 225][$i]]);
        }

        // [status, kind, method, reason]
        $plan = [
            ['paid', 'balance', 'Check', 'Credit balance after final bill'], ['paid', 'deposit', 'ACH', 'Deposit returned after 12 on-time payments'],
            ['paid', 'deposit', 'Check', 'Deposit returned at move-out'], ['paid', 'balance', 'Card', 'Overpayment refunded to the card used'],
            ['paid', 'balance', 'Check', 'Credit balance after final bill'], ['paid', 'deposit', 'ACH', 'Deposit returned after 12 on-time payments'],
            ['rejected', 'balance', 'Check', 'Requested refund of a pending credit'], ['rejected', 'deposit', 'ACH', 'Deposit refund requested before 12 payments'],
            ['approved', 'deposit', 'ACH', 'Deposit returned after 12 on-time payments'], ['approved', 'balance', 'Check', 'Credit balance after final bill'],
            ['requested', 'deposit', 'Check', 'Customer moving out of state'], ['requested', 'balance', 'Card', 'Paid twice by mistake'],
        ];
        $withCredit = Customer::where('balance', '<', 0)->whereDoesntHave('refunds')->get()->values();
        $withDeposit = Customer::where('deposit_held', '>', 0)->get()->values();
        $ci = $di = 0;
        foreach ($plan as $i => [$status, $kind, $method, $reason]) {
            $c = $kind === 'deposit' ? $withDeposit[$di++ % $withDeposit->count()] : ($withCredit[$ci++] ?? null);
            if (! $c) {
                continue;
            }
            $amount = $kind === 'deposit' ? (float) $c->deposit_held : abs($c->balance);
            $at = now()->subDays(mt_rand(2, 50))->setTime(mt_rand(8, 16), mt_rand(0, 59));
            $decidedAt = $status !== 'requested' ? $at->copy()->addHours(mt_rand(4, 40)) : null;
            $paidAt = $status === 'paid' ? $decidedAt->copy()->addDays(mt_rand(1, 4)) : null;
            $r = Refund::create(['customer_id' => $c->id, 'amount' => $amount, 'reason' => $reason, 'kind' => $kind, 'method' => $method, 'status' => $status,
                'requested_by' => $requesters[$i % 3], 'decided_by' => $decidedAt ? $finance : null, 'decided_at' => $decidedAt, 'paid_at' => $paidAt,
                'decision_note' => $status === 'rejected' ? ($kind === 'deposit' ? 'Only 7 of 12 on-time payments so far' : 'The credit has not posted yet') : null,
                'check_number' => $paidAt && $method === 'Check' ? (string) mt_rand(10400, 10999) : null]);
            $r->forceFill(['created_at' => $at, 'updated_at' => $paidAt ?? $decidedAt ?? $at])->saveQuietly();
            if ($paidAt) {
                $kind === 'deposit' ? $c->update(['deposit_held' => 0]) : $c->update(['balance' => 0]);
            }
            $c->notes()->create(['user_id' => $finance, 'author' => 'Finance Demo', 'category' => 'Payment', 'action' => 'Deposit Refund Request', 'priority' => 'Low',
                'body' => ucfirst($kind).' refund of $'.number_format($amount, 2).' '.$status.'.', 'created_at' => $paidAt ?? $decidedAt ?? $at, 'updated_at' => $paidAt ?? $decidedAt ?? $at]);
        }
    }

    /** Bounty: redemptions of every kind, with gift cards waiting to be sent and ones already sent. */
    private function rewards(): void
    {
        $offers = RewardOffer::where('stars', '>', 0)->where('effect', '!=', 'product')->get();
        $people = Customer::whereIn('status', ['Good - On Flow', 'Dropped - Churned'])->inRandomOrder()->limit(22)->get();
        foreach ($people as $i => $c) {
            $o = $offers[($i * 3) % $offers->count()];
            $earned = now()->subDays(mt_rand(30, 120));
            $at = $earned->copy()->addDays(mt_rand(5, 25))->setTime(mt_rand(8, 21), mt_rand(0, 59));
            // The stars that paid for it: an anniversary bonus earlier on
            StarEntry::create(['customer_id' => $c->id, 'reason' => 'Anniversary Bonus', 'stars' => $o->stars, 'created_at' => $earned, 'updated_at' => $earned]);
            $pending = $o->effect === 'gift' && $i % 3 === 0;
            StarEntry::create(['customer_id' => $c->id, 'reason' => 'Redeemed: '.$o->name, 'stars' => -$o->stars, 'reward_offer_id' => $o->id,
                'fulfillment' => $o->effect === 'gift' ? ($pending ? 'pending' : 'sent') : null, 'created_at' => $at, 'updated_at' => $at]);
            if ($o->effect === 'gift') {
                ContactLog::create(['customer_id' => $c->id, 'channel' => 'Email', 'template' => 'E-Gift Card: '.$o->name, 'status' => $pending ? 'queued' : 'sent',
                    'sent_at' => $pending ? null : $at->copy()->addDay(), 'created_at' => $at, 'updated_at' => $at]);
            } elseif ($o->effect === 'credit') {
                $e = LedgerEntry::create(['customer_id' => $c->id, 'kind' => 'credit', 'amount' => (float) $o->value, 'description' => $o->name, 'status' => 'applied',
                    'decided_by' => $this->uid('finance@example.com'), 'decided_at' => $at->copy()->addDay()]);
                $e->forceFill(['created_at' => $at, 'updated_at' => $at->copy()->addDay()])->saveQuietly();
            }
            $c->notes()->create(['author' => 'Customer (MyAccount)', 'category' => 'Rewards', 'action' => 'Redeem Stars', 'priority' => 'Low',
                'body' => 'Redeemed '.$o->stars.' stars: '.$o->name.'.', 'created_at' => $at, 'updated_at' => $at]);
            $c->syncStars();
        }
    }

    /** Bounty → Monthly Drawing: entries and a winner for each of the last six months. */
    private function drawing(): void
    {
        $prizes = ['$100 Visa Gift Card', 'Free Month of Electricity', 'ecobee Smart Thermostat', '$100 Amazon Gift Card', '$150 Bill Credit', 'YETI Cooler'];
        $ids = Customer::pluck('id');
        $pos = (int) ReferenceRow::where('table_key', 'monthly-drawing')->max('position');
        foreach (range(6, 1) as $back) {
            $m = now()->startOfMonth()->subMonths($back);
            $have = HistoryItem::where('model', 'ItemDrawingEntry_model')->whereBetween('created_at', [$m, $m->copy()->endOfMonth()])->pluck('customer_id');
            if ($have->isEmpty()) {
                foreach ($ids->shuffle()->take(mt_rand(20, 34)) as $id) {
                    HistoryItem::create(['customer_id' => $id, 'model' => 'ItemDrawingEntry_model', 'group' => 'Products', 'record_id' => $id, 'action' => 'created',
                        'summary' => 'Monthly drawing entry', 'data' => ['month' => $m->format('Y-m')], 'created_at' => $m->copy()->addMinutes(mt_rand(0, 60 * 24 * 27))]);
                }
                $have = HistoryItem::where('model', 'ItemDrawingEntry_model')->whereBetween('created_at', [$m, $m->copy()->endOfMonth()])->pluck('customer_id');
            }
            $winner = Customer::find($have->random());
            $drawn = $m->copy()->addMonth()->addDays(mt_rand(1, 4));
            ReferenceRow::create(['table_key' => 'monthly-drawing', 'position' => ++$pos, 'cells' => [$m->format('Y-m'), $prizes[$back - 1], $winner->account, $drawn->toDateString()]]);
            $winner->notes()->create(['user_id' => $this->uid('lead@example.com'), 'author' => 'CS Lead Demo', 'category' => 'Rewards', 'action' => 'Gift Card', 'priority' => 'Medium',
                'body' => 'Won the '.$m->format('Y-m').' monthly drawing: '.$prizes[$back - 1].'.', 'created_at' => $drawn, 'updated_at' => $drawn]);
        }
    }

    /** Sheriff → Integrations: recent calls for every API. */
    private function apiCalls(): void
    {
        $actions = [
            'AIG' => ['policy.quote', 'policy.bind', 'claim.status'], 'Amazon' => ['giftcard.create', 'giftcard.status'], 'Apple' => ['applepay.session', 'applepay.validate'],
            'Auth.net' => ['transaction.authCapture', 'transaction.void', 'customerProfile.create'], 'IBM' => ['weather.forecast', 'weather.history'],
            'Innowatts' => ['forecast.load', 'forecast.usage', 'meter.intervals'], 'Plaid' => ['link.token.create', 'auth.get', 'item.public_token.exchange'],
            'QuickBooks' => ['journalentry.create', 'customer.query', 'deposit.create'], 'SalesForce' => ['email.send', 'contact.upsert', 'journey.event'],
            'Twilio' => ['messages.create', 'messages.status', 'calls.create'], 'Open Models API' => ['chat.completions', 'embeddings'], 'Local Models' => ['generate', 'chat'],
        ];
        $ids = Customer::pluck('id');
        $rows = [];
        foreach ($actions as $api => $list) {
            $n = mt_rand(18, 40);
            for ($i = 0; $i < $n; $i++) {
                $fail = mt_rand(1, 100) <= 6;
                $rows[] = ['customer_id' => in_array($api, ['IBM', 'Innowatts', 'QuickBooks'], true) ? null : $ids->random(), 'api' => $api,
                    'action' => $list[array_rand($list)], 'status' => $fail ? (string) [400, 401, 429, 500, 503][mt_rand(0, 4)] : (mt_rand(0, 4) ? '200' : '201'),
                    'response_ms' => in_array($api, ['Open Models API', 'Local Models'], true) ? mt_rand(900, 6500) : mt_rand(80, 1400),
                    'created_at' => now()->subMinutes(mt_rand(5, 60 * 24 * 21))->format('Y-m-d H:i:s')];
            }
        }
        foreach (array_chunk($rows, 300) as $chunk) {
            ApiLog::insert($chunk);
        }
    }

    /** Corral → Reports: a few recent runs for everyone who can use Corral. */
    private function reportRuns(): void
    {
        foreach (User::where('active', true)->get()->filter(fn ($u) => $u->hasPerm('corral')) as $u) {
            foreach (['orders', 'notes', 'phonecalls'] as $key) {
                $def = Report::definition($key);
                $report = Report::make($key);
                foreach (range(1, mt_rand(2, 4)) as $k) {
                    $f = $report->defaults();
                    foreach (['start', 'from', 'date_from'] as $s) {
                        if (isset($f[$s])) {
                            $f[$s] = today()->subDays(30 * $k)->toDateString();
                        }
                    }
                    $f['output'] = $k % 2 ? 'screen' : 'summary';
                    $at = now()->subDays($k * mt_rand(1, 6))->setTime(mt_rand(8, 17), mt_rand(0, 59));
                    ReportRun::create(['user_id' => $u->id, 'app' => 'corral', 'report' => $key, 'title' => $def['title'], 'model' => $def['model'],
                        'params' => array_filter($f, fn ($v) => $v !== null && $v !== []), 'rows' => $report->query($f)->count(), 'status' => 'done',
                        'created_at' => $at, 'started_at' => $at, 'finished_at' => $at->copy()->addSeconds(mt_rand(1, 9))]);
                }
            }
        }
    }

    /** Every account gets texts and at least one phone call. */
    private function textsAndCalls(): void
    {
        $csr = User::where('email', 'csr@example.com')->first();
        $texts = [['Welcome', 'Welcome to Energy Texas! Your enrollment is in. We\'ll text you when service starts. Reply STOP to opt out.'],
            ['Bill Ready', 'Your Energy Texas bill is ready. Reply HELP for help or STOP to opt out.'],
            ['Payment Received', 'Thanks! We received your payment. Reply STOP to opt out.']];
        $calls = [['Balance Inquiry', 'I wanted to check what I owe this month.', 'Your current balance is on the last bill we sent; it\'s due on the date shown. Want me to text you a payment link?'],
            ['Enrollment Status', 'I signed up online, when does my service start?', 'Your order is with the utility now. Most switches finish in one to two business days; we\'ll text you when it\'s done.'],
            ['Move Out', 'I\'m moving next month and need to stop service.', 'I can set a move-out date for you. Your final bill comes about a week after that date.'],
            ['High Bill', 'My bill was higher than usual.', 'Usage went up with the heat this month. I can walk you through the usage chart in My Account.']];
        foreach (Customer::all() as $i => $c) {
            $phone = $c->phone ?? '(555) 555-01'.str_pad((string) ($c->id % 100), 2, '0', STR_PAD_LEFT);
            if (! ContactLog::where('customer_id', $c->id)->where('channel', 'SMS')->exists()) {
                $start = ($c->created_at ?? now()->subMonths(2))->copy();
                foreach ($texts as $k => [$tpl, $body]) {
                    $at = $start->copy()->addDays($k * 21 + 1)->setTime(mt_rand(9, 18), mt_rand(0, 59));
                    if ($at->isFuture() || ($k && in_array($c->status, ['Submitted', 'Pending - Credit', 'Pending - Deposit Due', 'Pending - Utility Not Answered', 'Rejected - By Utility'], true))) {
                        break;
                    }
                    ContactLog::create(['customer_id' => $c->id, 'channel' => 'SMS', 'template' => $tpl, 'body' => $body, 'status' => 'sent', 'sent_at' => $at,
                        'direction' => 'out', 'phone' => $phone, 'created_at' => $at, 'updated_at' => $at]);
                }
                $at = $start->copy()->addDays(2)->setTime(mt_rand(9, 18), mt_rand(0, 59));
                ContactLog::create(['customer_id' => $c->id, 'channel' => 'SMS', 'template' => 'Reply', 'body' => ['Thanks!', 'Got it, thank you', 'Ok great', 'When will service start?'][$i % 4],
                    'status' => 'received', 'sent_at' => $at, 'direction' => 'in', 'phone' => $phone, 'created_at' => $at, 'updated_at' => $at]);
            }
            if (! Phonecall::where('customer_id', $c->id)->exists()) {
                [$disp, $ask, $answer] = $calls[$i % count($calls)];
                $first = explode(' ', $c->name)[0];
                Phonecall::create(['customer_id' => $c->id, 'user_id' => $csr?->id, 'agent_id' => 'A1'.str_pad((string) (5 + $i % 7), 2, '0', STR_PAD_LEFT), 'phone' => $phone,
                    'direction' => 'inbound', 'started_at' => ($c->created_at ?? now())->copy()->addDays(3)->setTime(mt_rand(9, 17), mt_rand(0, 59)), 'duration_sec' => mt_rand(90, 600),
                    'disposition' => $disp, 'transcript' => "Agent: Thanks for calling, this is CSR. Who do I have the pleasure of speaking with?\nCustomer: Hi, this is {$first}.\nCustomer: {$ask}\nAgent: {$answer}\nCustomer: Okay, thanks.\nAgent: Is there anything else I can help with today?\nCustomer: No, that was it.\nAgent: Have a great day."]);
            }
        }
    }
}
