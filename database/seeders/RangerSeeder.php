<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\RangerAgent;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Ranger sample data (fictional): the ticket team (in-office staff and a 3rd-party call center, each
 * with managers) and three months of My Account support tickets with replies, notes, managers joining
 * in or taking over, and customers' 👍 / 👎 ratings.
 */
class RangerSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(4242);
        $byEmail = fn (string $e) => User::where('email', $e)->first();
        $team = [
            ['csr@example.com', 'office', 'agent'], ['morgan.placeholder@staff.example.com', 'office', 'agent'], ['jordan.sampleton@staff.example.com', 'office', 'agent'],
            ['riley.testwood@staff.example.com', 'office', 'agent'], ['quinn.mockridge@staff.example.com', 'office', 'agent'],
            ['lead@example.com', 'office', 'manager'], ['taylor.demoson@staff.example.com', 'office', 'manager'],
            ['alex.o@brightline.example.net', 'third_party', 'agent'], ['blair.a@brightline.example.net', 'third_party', 'agent'],
            ['cameron.w@brightline.example.net', 'third_party', 'agent'], ['devon.s@brightline.example.net', 'third_party', 'manager'],
            ['kai.f@brightline.example.net', 'third_party', 'agent'],
        ];
        $lead = $byEmail('lead@example.com');
        foreach ($team as $i => [$email, $t, $level]) {
            if ($u = $byEmail($email)) {
                $a = RangerAgent::create(['user_id' => $u->id, 'team' => $t, 'level' => $level, 'active' => $u->active, 'added_by' => $lead?->id]);
                $a->forceFill(['created_at' => now()->subDays(120 - $i * 6)])->save();
            }
        }
        $agents = RangerAgent::with('user')->where('active', true)->get();
        $workers = $agents->where('level', 'agent')->values();
        $managers = $agents->where('level', 'manager')->values();

        $scripts = [
            ['billing', 'Why is my bill higher this month?', 'My bill went from about $120 to $210 and nothing changed at home. Can someone check it?', 'I looked at your usage: it went from 1,050 kWh to 1,830 kWh during the heat wave, and the meter reads were actual reads. Your rate didn\'t change. Would a payment arrangement help this month?', 'Yes please, can I split it in two?', 'Done: half is due on this bill and half on the next one. You\'ll see it on your next statement.'],
            ['billing', 'Payment not showing', 'I paid on Monday from my bank but My Account still shows the balance.', 'Found it: your payment posted Tuesday morning and your balance is $0.00 now. My Account updates overnight.', null, null],
            ['billing', 'Change my due date', 'Can I move my due date to the 20th? I get paid mid-month.', 'I moved your due date to the 20th starting with your next bill.', null, null],
            ['service', 'Moving next month', 'I\'m moving to a new apartment across town on the 15th. How do I move my service?', 'I set up service at your new address starting the 15th and stopping at the old one on the 16th, so there\'s no gap. Your plan and rate come with you.', 'Perfect, thank you!', null],
            ['service', 'Start date for new service', 'I signed up last week. When does my service start?', 'Your switch is scheduled with the utility for the 3rd. You don\'t need to do anything; your lights stay on during the switch.', null, null],
            ['plan', 'Renewal options', 'My contract ends soon. What are my options to renew?', 'You can renew for 12 or 24 months. The 24-month plan is 0.4¢ less per kWh. I can switch you today with no fee; just reply with which you\'d like.', '24 months please.', 'All set: you\'re on the 24-month plan starting at the end of your current term.'],
            ['plan', 'Early termination fee question', 'If I cancel early, how much is the fee?', 'Your plan\'s cancellation fee is $150. It\'s waived if you\'re moving outside our service area; send a copy of your new lease and I\'ll waive it.', null, null],
            ['rewards', 'Stars missing', 'I paid on time but didn\'t get my stars this month.', 'Your on-time stars post two days after the payment clears. I see 25 stars posted this morning, so you\'re all caught up.', null, null],
            ['rewards', 'Gift card not received', 'I redeemed stars for a gift card a week ago and never got the email.', 'The gift card email went to an old address. I re-sent it to the email on your account just now.', 'Got it, thanks.', null],
            ['account', 'Can\'t sign in', 'My Account keeps saying my password is wrong.', 'I sent a password reset link to your email. It\'s good for 60 minutes.', null, null],
            ['account', 'Add my spouse to the account', 'Can my husband call in about the account too?', 'I added him as an authorized user. He\'ll need the account number when he calls.', null, null],
            ['outage', 'Power out since this morning', 'My power has been out since 7am. Is it you or the utility?', 'Outages are handled by your utility. I checked their outage map and crews are working in your area; the estimate is 2pm. You can text OUT to your utility for updates.', null, null],
            ['other', 'Paperless billing', 'Please stop mailing me paper bills.', 'Done: you\'re on paperless billing and you\'ll get 5 bonus stars on your next bill.', null, null],
        ];
        $badComments = ['Took too long to get an answer.', 'Still don\'t understand why my bill went up.', 'Had to ask twice.', 'The answer didn\'t fix my problem.'];
        $goodComments = ['Fast and friendly, thank you!', 'Super helpful.', 'Fixed it the same day.', 'Easy, thanks!', null, null, null];

        // Customers with a My Account login first, so the test sign-ins have tickets to look at
        $customers = Customer::whereNotNull('email')->orderByRaw('password is null')->orderBy('id')->get();
        for ($n = 0; $n < 140; $n++) {
            [$topic, $subject, $ask, $answer, $followUp, $final] = $scripts[$n % count($scripts)];
            $c = in_array($n, [1, 9, 18, 30, 45, 61], true) ? $customers[0] : $customers[1 + ($n * 7) % ($customers->count() - 1)];   // a few for the demo login
            // The first 24 are still being worked: new (nobody has them), open, or waiting on the customer
            $state = match (true) {
                $n < 8 => 'new', $n < 17 => 'open', $n < 24 => 'waiting', default => $n % 11 === 0 ? 'closed' : 'solved'
            };
            $opened = ($n < 24 ? now()->subMinutes(mt_rand(20, 60 * ($n < 8 ? 10 : 70))) : now()->subMinutes(mt_rand(60 * 6, 60 * 24 * 90)))->startOfMinute();
            // A bit over a third go to the 3rd-party team
            $pool = $workers->filter(fn ($a) => $a->team === ($n % 8 < 3 ? 'third_party' : 'office'))->values();
            $owner = $state === 'new' ? null : $pool[$n % $pool->count()]->user;
            $t = SupportTicket::create(['customer_id' => $c->id, 'name' => $c->name, 'email' => $c->email, 'subject' => $subject, 'topic' => $topic, 'channel' => 'My Account',
                'status' => $state, 'priority' => $topic === 'outage' ? 'high' : ($n % 17 === 0 ? 'urgent' : ($n % 6 === 0 ? 'low' : 'normal')),
                'assigned_to' => $owner?->id, 'created_at' => $opened, 'updated_at' => $opened, 'last_activity_at' => $opened]);
            $at = $opened->copy();
            $msg = function (string $author, string $body, ?User $u = null, int $minutes = 0) use ($t, &$at) {
                $at = $at->copy()->addMinutes($minutes);
                $t->messages()->create(['author' => $author, 'body' => $body, 'user_id' => $u?->id, 'created_at' => $at]);
            };
            $msg('customer', $ask);
            if ($owner) {
                $slow = $pool[0]->team === 'third_party' ? 1.6 : 1;
                $msg('system', $owner->name.' picked this up.', $owner, (int) (mt_rand(5, 240) * $slow));
                if ($n % 5 === 1) {
                    $msg('note', 'Checked the meter reads with the utility before replying; both actual reads.', $owner, mt_rand(2, 20));
                }
                $msg('agent', 'Hi '.Str::before($c->name, ' ').', '.$answer, $owner, mt_rand(3, 30));
                $t->forceFill(['first_response_at' => $at])->save();
                // Managers: some tickets they joined in on, a few they took over
                if ($n % 9 === 2 && $managers->isNotEmpty()) {
                    $m = $managers->firstWhere('team', $pool[0]->team) ?? $managers->first();
                    $t->watchers()->attach($m->user_id, ['joined_at' => $at->copy()->addMinutes(10)]);
                    $msg('system', $m->user->name.' joined in.', $m->user, 10);
                    $msg('note', 'Looks right to me. If they push back, offer the deferred payment plan.', $m->user, 5);
                } elseif ($n % 23 === 4 && $managers->isNotEmpty()) {
                    $m = $managers->first();
                    $t->watchers()->attach($owner->id, ['joined_at' => $at->copy()->addMinutes(15)]);
                    $msg('system', $m->user->name.' took over this ticket from '.$owner->name.'.', $m->user, 15);
                    $t->forceFill(['assigned_to' => $m->user_id])->save();
                    $owner = $m->user;
                }
                if ($followUp && $state !== 'waiting') {
                    $msg('customer', $followUp, null, mt_rand(30, 600));
                    if ($final) {
                        $msg('agent', $final, $owner, mt_rand(10, 180));
                    }
                }
            }
            $t->forceFill(['last_activity_at' => $at])->save();
            if (in_array($state, ['solved', 'closed'], true)) {
                $t->forceFill(['solved_at' => $at->copy()->addMinutes(5)])->save();
                $msg('system', $owner->name.' set the status to Solved.', $owner, 5);
                // Most customers rate; the 3rd-party team rates a little lower
                if ($n % 4 !== 3) {
                    $third = RangerAgent::where('user_id', $t->assigned_to)->value('team') === 'third_party';
                    $good = mt_rand(1, 100) <= ($third ? 82 : 93);
                    $comment = $good ? $goodComments[$n % count($goodComments)] : $badComments[$n % count($badComments)];
                    $ratedAt = $at->copy()->addHours(mt_rand(1, 30));
                    $t->forceFill(['rating' => $good ? 'good' : 'bad', 'rating_comment' => $comment, 'rated_at' => $ratedAt->isFuture() ? now() : $ratedAt])->save();
                }
            }
        }
    }
}
