<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\ContactLog;
use App\Models\CustomerProduct;
use App\Models\EmailTemplate;
use App\Models\PlanTerm;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Sample (fictional) campaigns with goals and results: sent ones with opens, clicks and conversions, a draft and a scheduled one. */
class CampaignSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(31);
        $admin = User::where('email', 'admin@example.com')->first();
        $tpl = fn ($like) => EmailTemplate::where('name', 'like', $like)->value('id');

        // The two from the marketing seed data get goals
        $renewal = Campaign::where('name', 'Fall Renewal Reminder')->first();
        $renewal?->update(['goal' => 'renewal', 'description' => 'Contracts ending this fall: renew before month-to-month rates kick in.', 'msid' => '130001', 'cap_days' => 7]);
        if ($renewal) {
            foreach ($renewal->messages()->with('customer')->limit(3)->get() as $m) {
                $this->term($m->customer, $renewal->sent_at->copy()->addDays(mt_rand(1, 6)));
            }
        }
        Campaign::where('name', 'Peak Perks Enrollment')->update(['goal' => 'product', 'product' => 'Peak Perks', 'description' => 'Summer is over, but enrolling now gets customers ready for next season.']);

        // Sent: AutoPay push by text, with sign-ups
        $autopay = Campaign::create(['name' => 'AutoPay Push', 'channel' => 'SMS', 'goal' => 'product', 'product' => 'AutoPay', 'cap_days' => 7, 'created_by' => $admin?->id,
            'message' => 'Hi {{first_name}}, never miss a due date: turn on AutoPay in My Account and earn 50 bonus stars. Reply STOP to opt out.',
            'audience' => ['autopay' => 'no'], 'status' => 'sent', 'sent_at' => now()->subDays(12)->setTime(10, 0)]);
        $this->deliver($autopay, 18, fn ($c, $i) => $i % 4 === 0 ? CustomerProduct::create(['customer_id' => $c->id, 'product' => 'AutoPay', 'created_at' => $autopay->sent_at->copy()->addDays(mt_rand(0, 5))]) : null);

        // Sent: win-back email
        $winback = Campaign::create(['name' => 'Come Back to Energy Texas', 'channel' => 'Email', 'goal' => 'winback', 'email_template_id' => $tpl('%Winback%') ?? $tpl('%Renewal Offer%'), 'cap_days' => 14,
            'description' => 'Customers who left in the last year: $50 credit to come back.', 'promo_code' => PromoCode::value('code'), 'created_by' => $admin?->id,
            'audience' => ['statuses' => ['Dropped - Churned', 'Moved Out']], 'status' => 'sent', 'sent_at' => now()->subDays(20)->setTime(9, 30)]);
        $this->deliver($winback, 10, fn ($c, $i) => $i === 1 ? $this->term($c, $winback->sent_at->copy()->addDays(3)) : null);

        // Scheduled: a rewards text for this weekend
        Campaign::create(['name' => 'Double Stars Weekend', 'channel' => 'SMS', 'goal' => 'rewards', 'cap_days' => 7, 'created_by' => $admin?->id,
            'message' => 'Hi {{first_name}}, this weekend only: redeem stars for gift cards at 2x value in My Account. Reply STOP to opt out.',
            'audience' => ['statuses' => ['Good - On Flow']], 'status' => 'scheduled', 'scheduled_at' => now()->next('Saturday')->setTime(9, 0)]);
    }

    /** A new plan term (a renewal or a returning customer's new plan) ordered at $at. */
    private function term($c, $at): PlanTerm
    {
        return PlanTerm::create(['customer_id' => $c->id, 'plan_id' => $c->plan_id, 'rate_class' => $c->type === 'Residential' ? 'Residential' : 'Commercial',
            'energy_charge' => 0.11250000, 'rate_2000' => 14.900, 'ordered_at' => $at, 'contract_start' => $at->copy()->addDays(14)->toDateString(), 'status' => 'not started']);
    }

    /** Messages to the first $n customers of the audience, with opens and clicks for email, and the conversion callback. */
    private function deliver(Campaign $c, int $n, \Closure $convert): void
    {
        foreach ($c->audienceQuery()->with('plan')->limit($n)->get() as $i => $cust) {
            $opened = $c->channel === 'Email' && mt_rand(1, 100) <= 46 ? $c->sent_at->copy()->addHours(mt_rand(1, 60)) : null;
            ContactLog::create(['customer_id' => $cust->id, 'campaign_id' => $c->id, 'channel' => $c->channel, 'template' => $c->channel === 'Email' ? EmailTemplate::find($c->email_template_id)?->name : 'Campaign: '.$c->name,
                'body' => $c->channel === 'SMS' ? $c->textFor($cust) : null, 'phone' => $c->channel === 'SMS' ? $cust->phone : null, 'status' => 'sent', 'sent_at' => $c->sent_at,
                'opened_at' => $opened, 'clicked_at' => $opened && mt_rand(1, 100) <= 35 ? $opened->copy()->addMinutes(mt_rand(1, 30)) : null,
                'created_at' => $c->sent_at, 'updated_at' => $c->sent_at]);
            $convert($cust, $i);
        }
        $c->update(['recipients' => $c->messages()->count()]);
    }
}
