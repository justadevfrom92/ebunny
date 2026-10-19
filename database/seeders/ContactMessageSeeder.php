<?php

namespace Database\Seeders;

use App\Models\ContactMessage;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Sample (fictional) Contact Us messages for Corral → Web Messages: some from customers, some from prospects; some answered. */
class ContactMessageSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(23);
        $agents = User::whereHas('role', fn ($q) => $q->where('perms', 'like', '%corral%'))->where('active', true)->get();
        $customers = Customer::whereNotNull('email')->whereNull('password')->orderBy('id')->limit(40)->get();
        $prospects = [['Jordan', 'Reyes'], ['Casey', 'Nguyen'], ['Morgan', 'Patel'], ['Taylor', 'Brooks'], ['Riley', 'Chen'], ['Avery', 'Lopez'], ['Quinn', 'Foster'], ['Drew', 'Kim']];
        $messages = [
            ['billing', 'My bill this month is almost double last month. I have not changed anything at home. Can someone look at my usage and tell me if the meter read is right?', 'Thanks for writing in. Your usage went up from 1,180 kWh to 2,240 kWh with the heat wave; the meter read was an actual read, not an estimate. I have set up a payment arrangement that splits this bill over your next two bills.'],
            ['billing', 'I paid my bill on the 3rd but My Account still shows a balance. I have the confirmation number from my bank.', 'I found your payment: it posted on the 4th and your balance is now $0.00. My Account updates overnight, so it should show correctly now.'],
            ['billing', 'Can I change my due date to the 15th? I get paid mid-month.', null],
            ['billing', 'Why was I charged a late fee when I am on AutoPay?', null],
            ['signup', 'I am moving to Dallas on the 28th. Can I start service at the new address before I cancel at the old one?', 'Yes. I scheduled service at your new address to start on the 27th and stop at your old address on the 29th, so you will not be without power on moving day.'],
            ['signup', 'Do you serve 77449? I want the 12-month fixed plan I saw on your website.', null],
            ['signup', 'I submitted my sign-up yesterday but did not get a confirmation email.', null],
            ['business', 'We have three restaurant locations in Houston. Can we get one bill for all three, and is there a business rate for 36 months?', null],
            ['business', 'Our office lease ends in March. What happens to our contract if we move?', 'If you move within our service area we transfer your plan to the new address with no fee. If you move outside it, the early termination fee is waived with proof of the move.'],
            ['rewards', 'I referred my neighbor last month but never got the bill credit. Their account is active now.', null],
            ['rewards', 'How do I use my stars for a gift card? The button is greyed out.', 'Gift cards start at 500 stars and you have 460, so the button unlocks after your next on-time payment (25 stars). Bill credits are available now at 100 stars.'],
            ['general', 'Is there an outage in my area? My power has been out since 6am.', 'Outages are handled by your utility, CenterPoint: call 713-207-2222 or text OUT to 444-111. I checked their outage map and crews are on site in your area.'],
            ['general', 'Please stop sending me paper bills. I already signed up for paperless.', null],
            ['general', 'Can you add my spouse as an authorized user on the account so they can call in?', null],
            ['general', 'What is the ESIID for my house? My solar installer is asking for it.', null],
            ['billing', 'I got a disconnect notice but I set up a payment arrangement last week on the phone.', null],
            ['signup', 'Your website says I need a deposit. Can I avoid it with AutoPay?', 'Yes: with AutoPay and paperless billing your deposit drops to $0 for your credit tier. I have updated your enrollment.'],
            ['business', 'Can I get a quote for 4 meters, about 18,000 kWh a month total?', null],
        ];
        // Recent messages (some still open), then older ones from the last few months, all handled
        foreach ([...$messages, ...$messages] as $i => [$topic, $text, $reply]) {
            $old = $i >= count($messages);
            $fromCustomer = $i % 3 !== 2 && $customers->isNotEmpty();
            $c = $fromCustomer ? $customers[$i % $customers->count()] : null;
            [$first, $last] = $c ? [$c->first_name ?: explode(' ', $c->name)[0], $c->last_name ?: (explode(' ', $c->name)[1] ?? '')] : $prospects[$i % count($prospects)];
            $at = $old ? now()->subDays(mt_rand(14, 90))->setTime(mt_rand(7, 21), mt_rand(0, 59)) : now()->subHours(mt_rand(2, 24 * 12))->startOfMinute();
            $done = $old || $reply !== null || mt_rand(1, 5) === 1;
            $reply ??= $old && $i % 2 ? 'Thanks for reaching out. I took care of this on your account; reply here if anything still looks wrong.' : null;
            $agent = $agents->isNotEmpty() ? $agents->random() : null;
            ContactMessage::create([
                'first_name' => $first, 'last_name' => $last, 'email' => $c?->email ?? strtolower($first.'.'.$last).'@example.com',
                'phone' => $c?->phone ?? '(555) 555-0'.str_pad((string) (130 + $i), 3, '0', STR_PAD_LEFT), 'topic' => $topic, 'message' => $text,
                'ip' => '198.51.100.'.(20 + $i % 200), 'customer_id' => $c?->id,
                'reply' => $reply ? 'Hi '.$first.",\n\n".$reply."\n\n".($agent?->name ?? 'Customer Care') : null,
                'replied_at' => $reply ? $at->copy()->addHours(mt_rand(1, 20)) : null, 'replied_by' => $reply ? $agent?->id : null,
                'handled_at' => $done ? $at->copy()->addHours(mt_rand(1, 26)) : null, 'handled_by' => $done ? $agent?->id : null,
                'created_at' => $at, 'updated_at' => $at,
            ]);
        }
    }
}
