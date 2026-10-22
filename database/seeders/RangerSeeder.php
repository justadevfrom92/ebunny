<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Note;
use App\Models\RangerAgent;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Support\Pdf;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ranger sample data (fictional): the ticket team (in-office staff and a 3rd-party call center, each
 * with managers) and three months of support tickets — from My Account, email and (most often) phone
 * calls — with replies, attachments, notes, managers joining in or taking over, and 👍 / 👎 ratings.
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
            ['3p.csr@example.com', 'third_party', 'agent'], ['3p.manager@example.com', 'third_party', 'manager'], ['3p.admin@example.com', 'third_party', 'company_admin'],
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
        Storage::disk('local')->deleteDirectory('ranger');
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
            $owner = $state === 'new' ? null : $pool[($n + intdiv($n, 8)) % $pool->count()]->user;
            // Lots of phone calls, some emails, the rest from My Account (new, unanswered ones can't be calls)
            $channel = $n < 8 ? ($n % 3 === 1 ? 'Email' : 'My Account') : (in_array($n % 9, [0, 2, 4, 6], true) ? 'Phone' : (in_array($n % 9, [1, 5], true) ? 'Email' : 'My Account'));
            $via = ['My Account' => 'portal', 'Email' => 'email', 'Phone' => 'phone'][$channel];
            $t = SupportTicket::create(['customer_id' => $c->id, 'name' => $c->name, 'email' => $c->email, 'subject' => $subject, 'topic' => $topic, 'channel' => $channel,
                'status' => $state, 'priority' => $topic === 'outage' ? 'high' : ($n % 17 === 0 ? 'urgent' : ($n % 6 === 0 ? 'low' : 'normal')),
                'assigned_to' => $owner?->id, 'created_at' => $opened, 'updated_at' => $opened, 'last_activity_at' => $opened]);
            $at = $opened->copy();
            $msg = function (string $author, string $body, ?User $u = null, int $minutes = 0, string $priority = 'Low', array $extra = []) use ($t, &$at): ?SupportTicketMessage {
                $at = $at->copy()->addMinutes($minutes);
                if ($author === 'note') {
                    // Internal notes are account notes (category Ticket) linked to the ticket
                    Note::create(['customer_id' => $t->customer_id, 'user_id' => $u->id, 'author' => $u->name, 'category' => 'Ticket', 'action' => 'Internal Note',
                        'disposition' => $t->number(), 'priority' => $priority, 'body' => $body, 'support_ticket_id' => $t->id, 'created_at' => $at, 'updated_at' => $at]);

                    return null;
                }

                return $t->messages()->create(['author' => $author, 'body' => $body, 'user_id' => $u?->id, 'created_at' => $at] + $extra);
            };
            $mail = fn (string $who) => $via === 'email' ? ['via' => 'email', 'contact' => $c->email] : [];
            $call = fn (string $dir) => ['via' => 'phone', 'direction' => $dir, 'duration' => mt_rand(90, 1100), 'contact' => $c->phone ?? '(555) 555-0100'];

            if ($channel === 'Phone') {
                // The agent who took the call opened the ticket and logged it
                $msg('agent', "Called about: {$ask}\n\nWhat I did: {$answer}", $owner, 0, 'Low', $call('in'));
                $t->forceFill(['first_response_at' => $at])->save();
                if ($n % 3 !== 2) {
                    $msg('note', ['Verified identity with the last 4 of the account and the service address.', 'Checked the meter reads with the utility while they were on the line; both actual reads.',
                        'Customer was polite; prefers a call back after 5pm.'][$n % 3], $owner, mt_rand(2, 20), ['Low', 'Medium', 'Low'][$n % 3]);
                }
                if ($followUp && $state !== 'waiting') {
                    $msg('agent', 'Called back to confirm. They said: "'.$followUp.'"'.($final ? "\n\n".$final : ''), $owner, mt_rand(60, 900), 'Low', $call('out'));
                } elseif ($state === 'waiting') {
                    $msg('agent', 'Called back, no answer. Left a voicemail asking them to call us.', $owner, mt_rand(60, 600), 'Low', $call('out') + ['duration' => mt_rand(20, 60)]);
                }
            } else {
                $first = $msg('customer', $ask, null, 0, 'Low', $mail('customer'));
                if ($n % 3 === 1) {
                    SupportTicketAttachment::store($first, $this->customerFile($topic, $c), null, true);
                }
            }
            if ($owner && $channel !== 'Phone') {
                $slow = $pool[0]->team === 'third_party' ? 1.6 : 1;
                $msg('system', $owner->name.' picked this up.', $owner, (int) (mt_rand(5, 240) * $slow));
                if ($n % 2 === 1) {
                    $msg('note', $n % 4 === 1 ? 'Checked the meter reads with the utility before replying; both actual reads.' : 'Looked at the last 3 bills and payments before answering.', $owner, mt_rand(2, 20));
                }
                $msg('agent', 'Hi '.Str::before($c->name, ' ').', '.$answer, $owner, mt_rand(3, 30), 'Low', $mail('agent'));
                $t->forceFill(['first_response_at' => $at])->save();
                // Managers: some tickets they joined in on, a few they took over
                if ($n % 9 === 2 && $managers->isNotEmpty()) {
                    $m = $managers->firstWhere('team', $pool[0]->team) ?? $managers->first();
                    $t->watchers()->attach($m->user_id, ['joined_at' => $at->copy()->addMinutes(10)]);
                    $msg('system', $m->user->name.' joined in.', $m->user, 10);
                    $msg('note', 'Looks right to me. If they push back, offer the deferred payment plan.', $m->user, 5, 'Medium');
                } elseif ($n % 23 === 4 && $managers->isNotEmpty()) {
                    $m = $managers->first();
                    $t->watchers()->attach($owner->id, ['joined_at' => $at->copy()->addMinutes(15)]);
                    $msg('system', $m->user->name.' took over this ticket from '.$owner->name.'.', $m->user, 15);
                    $t->forceFill(['assigned_to' => $m->user_id])->save();
                    $owner = $m->user;
                }
                if ($followUp && $state !== 'waiting') {
                    $more = $msg('customer', $followUp, null, mt_rand(30, 600), 'Low', $mail('customer'));
                    if ($n % 5 === 2) {
                        SupportTicketAttachment::store($more, $this->customerFile($n % 2 ? 'other' : $topic, $c), null, true);
                    }
                    if ($final) {
                        $msg('agent', $final, $owner, mt_rand(10, 180), 'Low', $mail('agent'));
                    }
                }
            }
            // A few open tickets where the agent asked for a manager
            if ($owner && in_array($n, [9, 13, 19, 22], true) && RangerAgent::where('user_id', $owner->id)->value('level') !== 'manager') {
                $why = ['Customer is upset and wants to talk to a manager.', 'Not sure I can approve this; need a manager to OK it.', null, 'Second time they wrote in about this.'][$n % 4];
                $msg('system', $owner->name.' asked for a manager'.($why ? ': '.$why : '.'), $owner, 4);
                $t->forceFill(['manager_requested_at' => $at, 'manager_requested_by' => $owner->id, 'manager_request_note' => $why])->save();
            }
            $t->forceFill(['last_activity_at' => $at])->save();
            if (in_array($state, ['solved', 'closed'], true)) {
                $t->forceFill(['solved_at' => $at->copy()->addMinutes(5)])->save();
                $closer = User::find($t->assigned_to);
                $msg('system', $closer->name.' set the status to Solved.', $closer, 5);
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

    /** What a customer might attach (fictional): a photo of their bill, a lease, a screenshot or a note. */
    private function customerFile(string $topic, Customer $c): array
    {
        if (in_array($topic, ['service', 'plan'], true)) {
            $pdf = (new Pdf)->text(54, 70, 'Residential Lease (sample)', 18, true)->text(54, 100, 'Tenant: '.$c->name)->text(54, 116, 'Move-in date: the 15th')
                ->text(54, 132, 'This is sample data for testing. Not a real lease.', 9);

            return ['lease.pdf', $pdf->output('Lease'), 'application/pdf'];
        }
        if ($topic === 'other') {
            return ['details.txt', "Account {$c->account}\nPlease use the email on file.\n(Sample file for testing.)\n", 'text/plain'];
        }
        $img = imagecreatetruecolor(520, 300);
        imagefill($img, 0, 0, imagecolorallocate($img, 250, 250, 248));
        $ink = imagecolorallocate($img, 30, 40, 70);
        imagefilledrectangle($img, 0, 0, 520, 44, imagecolorallocate($img, 0, 174, 239));
        imagestring($img, 5, 16, 14, $topic === 'account' ? 'My Account - Sign in' : 'SAMPLE ENERGY - STATEMENT', imagecolorallocate($img, 255, 255, 255));
        $lines = $topic === 'account' ? ['Email: '.$c->email, 'Password: ********', '', 'X  The password you entered is incorrect.']
            : ['Account '.$c->account, 'Usage: 1,830 kWh', 'Amount due: $210.44', '', 'SAMPLE IMAGE - NOT A REAL BILL'];
        foreach ($lines as $i => $l) {
            imagestring($img, 4, 20, 70 + $i * 30, $l, $ink);
        }
        ob_start();
        imagepng($img);

        return [$topic === 'account' ? 'screenshot.png' : 'bill-photo.png', (string) ob_get_clean(), 'image/png'];
    }
}
