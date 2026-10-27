<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\EmailCategory;
use App\Models\EmailPart;
use App\Models\EmailSuppression;
use App\Models\EmailTemplate;
use App\Models\EmailTestSend;
use App\Models\HistoryItem;
use App\Models\MarketingChannel;
use App\Models\PromoCode;
use App\Models\Refund;
use App\Models\RewardOffer;
use App\Models\RewardRule;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Bounty offers and rules, Rodeo templates/channels/promo codes/survey/campaign, and sample Caboose refunds. */
class MarketingRewardsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (DatabaseSeeder::data('reward_offers') as $o) {
            RewardOffer::create($o);
        }
        foreach ([
            ['on_time_payment', 'Bill Payment', 0.05, true], ['loyalty_monthly', 'Monthly Loyalty', 10, false],
            ['autopay_monthly', 'AutoPay Bonus', 5, false], ['paperless_signup', 'Paperless Signup', 10, false], ['referral', 'Referral', 50, false],
        ] as [$key, $label, $stars, $perDollar]) {
            RewardRule::create(['key' => $key, 'label' => $label, 'stars' => $stars, 'per_dollar' => $perDollar]);
        }

        foreach (DatabaseSeeder::data('email_templates') as $t) {
            EmailTemplate::create($t);
        }
        // Rodeo → Emails → Categories
        foreach ([
            ['Account', 'Welcome letters, confirmations and sign-in emails', '#00AEEF', ['Welcome Letter', 'Welcome Letter - Renewed', 'Renewal - Confirmation', 'Freedom Flex - Confirmation', 'MyAccount - Password Reset']],
            ['Billing', 'Bills, payments and collections notices', '#2e7d32', ['Bill Ready', 'Payment Made', 'Billing - Resi - Payment Made', 'Disconnect Notice']],
            ['Marketing', 'Offers and campaigns; only to opted-in customers', '#f39c12', ['Renewal Offer', 'Winback Offer']],
            ['Rewards', 'Peak Perks and Bounty emails', '#8e44ad', ['MyAcct - Peak Perks - Welcome to Peak Perks']],
            ['Surveys', 'Survey invitations', '#c0392b', ['Customer Survey', 'Survey - 90 Day Check-In', 'Survey - Farewell']],
            ['Service', 'One-off messages from Customer Service', '#607d8b', ['Custom Message']],
        ] as $i => [$name, $desc, $color, $names]) {
            $cat = EmailCategory::create(['name' => $name, 'description' => $desc, 'color' => $color, 'position' => $i + 1]);
            EmailTemplate::whereIn('name', $names)->update(['email_category_id' => $cat->id]);
        }
        $this->headersAndFooters();
        foreach ([['1001', 'Organic Website', 'Organic'], ['2001', 'Call Center', 'Agent'], ['3001', 'Google Ads Pay Per Click', 'Paid'],
            ['32001', 'Partner Referral Network', 'Partner'], ['41001', 'Broker - Sample Brokerage', 'Broker'], ['52001', 'Community Event', 'Partner']] as [$msid, $name, $type]) {
            MarketingChannel::create(['msid' => $msid, 'name' => $name, 'type' => $type]);
        }
        PromoCode::create(['code' => 'FALL25', 'description' => '$25 bill credit for fall sign-ups', 'credit' => 25, 'starts_on' => today()->subDays(20), 'ends_on' => today()->addDays(40)]);
        PromoCode::create(['code' => 'WELCOME', 'description' => 'Website welcome offer', 'credit' => 10]);

        $this->call(SurveySeeder::class);

        $admin = User::where('email', 'admin@example.com')->first();
        $tpl = EmailTemplate::where('name', 'Renewal Offer')->first();
        $campaign = Campaign::create(['name' => 'Fall Renewal Reminder', 'channel' => 'Email', 'email_template_id' => $tpl?->id, 'audience' => ['statuses' => ['Good - On Flow']],
            'status' => 'sent', 'sent_at' => now()->subDays(6), 'created_by' => $admin?->id]);
        $recipients = $campaign->audienceQuery()->limit(14)->get();
        foreach ($recipients as $i => $c) {
            // Two bounce, and their addresses go on the suppression list (Rodeo → Emails)
            $bounced = in_array($i, [4, 10], true) && $c->email && $c->account !== '1219000000';
            ContactLog::create(['customer_id' => $c->id, 'campaign_id' => $campaign->id, 'channel' => 'Email', 'template' => $tpl->name, 'status' => 'sent',
                'sent_at' => now()->subDays(6), 'dropped_at' => $bounced ? now()->subDays(6)->addMinutes(2) : null,
                'opened_at' => ! $bounced && $i % 2 ? now()->subDays(5) : null, 'clicked_at' => ! $bounced && $i % 4 === 1 ? now()->subDays(5) : null, 'created_at' => now()->subDays(6)]);
            if ($bounced) {
                EmailSuppression::create(['email' => $c->email, 'reason' => 'bounce', 'customer_id' => $c->id, 'note' => 'Mailbox does not exist (550)', 'created_at' => now()->subDays(6)]);
            }
        }
        foreach ([['unsubscribe', 'Clicked unsubscribe in a Renewal Offer email', 9], ['complaint', 'Marked a Winback Offer email as spam', 14], ['manual', 'Asked on the phone to stop all emails', 3]] as [$reason, $note, $days]) {
            $c = Customer::whereNotNull('email')->where('account', '!=', '1219000000')->whereNotIn('email', EmailSuppression::select('email'))->orderByDesc('id')->skip($days)->first();
            $c && EmailSuppression::create(['email' => $c->email, 'reason' => $reason, 'customer_id' => $c->id, 'note' => $note,
                'user_id' => $reason === 'manual' ? User::where('email', 'csr@example.com')->value('id') : null, 'created_at' => now()->subDays($days)]);
        }
        EmailSuppression::create(['email' => 'old-address@example.net', 'reason' => 'bounce', 'note' => 'Address from a closed account', 'created_at' => now()->subDays(40)]);
        $campaign->update(['recipients' => $recipients->count()]);
        Campaign::create(['name' => 'Peak Perks Enrollment', 'channel' => 'Email', 'email_template_id' => EmailTemplate::where('name', 'like', '%Peak Perks%')->value('id'),
            'audience' => ['statuses' => ['Good - On Flow'], 'without_product' => 'Peak Perks'], 'created_by' => $admin?->id]);

        // Caboose: a refund waiting for approval for an account with a credit balance
        $credit = Customer::where('balance', '<', 0)->first() ?? tap(Customer::where('status', 'Dropped - Churned')->first(), fn ($c) => $c?->update(['balance' => -42.18]));
        if ($credit) {
            Refund::create(['customer_id' => $credit->id, 'amount' => abs($credit->balance), 'reason' => 'Credit balance after final bill', 'method' => 'Check',
                'requested_by' => User::where('email', 'csr@example.com')->value('id')]);
        }
        Customer::where('status', 'Pending - Deposit Due')->update(['deposit_due' => 150]);

        // Bounty → Monthly Drawing: entries through this month and last month
        foreach ([now()->startOfMonth(), now()->startOfMonth()->subMonth()] as $m) {
            $span = max(60, (int) $m->diffInMinutes($m->isSameMonth(now()) ? now() : $m->copy()->endOfMonth(), true));
            foreach (Customer::inRandomOrder()->limit($m->isSameMonth(now()) ? 18 : 31)->get() as $c) {
                HistoryItem::create(['customer_id' => $c->id, 'model' => 'ItemDrawingEntry_model', 'group' => 'Products', 'record_id' => $c->id, 'action' => 'created',
                    'summary' => 'Monthly drawing entry', 'data' => ['month' => $m->format('Y-m')], 'created_at' => $m->copy()->addMinutes(mt_rand(0, $span - 1))]);
            }
        }

        // A test send of the welcome email to the marketing team
        $marketing = User::where('email', 'marketing@example.com')->first();
        if ($marketing && $welcome = EmailTemplate::orderBy('id')->first()) {
            EmailTestSend::create(['email_template_id' => $welcome->id, 'user_id' => $marketing->id, 'mode' => 'team', 'target' => $marketing->role?->name.' team',
                'recipients' => [['email' => $marketing->email, 'name' => $marketing->name, 'status' => 'logged']], 'status' => 'logged', 'created_at' => now()->subDay()]);
        }
    }

    /** Rodeo → Headers & Footers: a few of each (sample wording), with Billing and Marketing templates picking their own. */
    private function headersAndFooters(): void
    {
        $brand = e(config('brand.name'));
        $wrap = 'font-family:Arial,sans-serif;max-width:600px;margin:0 auto';
        $parts = [
            ['header', 'Standard Header', true, '<div style="'.$wrap.';background:#102247;padding:16px 24px"><a href="/" style="color:#fff;font-weight:700;font-size:20px;text-decoration:none">'.$brand.'</a></div>'],
            ['header', 'Rewards Header', false, '<div style="'.$wrap.';background:#00AEEF;padding:16px 24px;color:#fff"><a href="/myaccount/rewards" style="color:#fff;font-weight:700;font-size:20px;text-decoration:none">Rangler Rewards</a><div style="font-size:13px">from '.$brand.'</div></div>'],
            ['header', 'Billing Header', false, '<div style="'.$wrap.';border-bottom:3px solid #102247;padding:14px 24px"><a href="/" style="color:#102247;font-weight:700;font-size:18px;text-decoration:none">'.$brand.'</a> <span style="float:right;color:#555;font-size:13px">Account {{account}}</span></div>'],
            ['header', 'None', false, ''],
            ['footer', 'Standard Footer', true, '<div style="'.$wrap.';padding:20px 24px;text-align:center"><a href="/myaccount" style="display:inline-block;background:#00AEEF;color:#fff;padding:12px 22px;border-radius:6px;text-decoration:none;font-weight:700">Go to My Account</a></div>'
                .'<div style="'.$wrap.';border-top:1px solid #ddd;padding:14px 24px;font-size:12px;color:#666;text-align:center"><a href="/myaccount/pay">Pay My Bill</a> · <a href="/plans">See Plans</a> · <a href="/unsubscribe">Unsubscribe</a> · <a href="/privacy-policy">Privacy</a><br>'.$brand.' · 1000 Sample Ave, Houston, TX 77002 · PUCT Cert. No. XXXXX</div>'],
            ['footer', 'Billing Footer', false, '<div style="'.$wrap.';padding:20px 24px;text-align:center"><a href="/myaccount/pay" style="display:inline-block;background:#c8102e;color:#fff;padding:12px 22px;border-radius:6px;text-decoration:none;font-weight:700">Pay Now</a><div style="font-size:13px;color:#555;margin-top:10px">Never miss a due date: <a href="/myaccount/methods">turn on AutoPay</a>.</div></div>'
                .'<div style="'.$wrap.';border-top:1px solid #ddd;padding:14px 24px;font-size:12px;color:#666;text-align:center">Questions about your bill? Call 1-800-555-0100. · <a href="/privacy-policy">Privacy</a></div>'],
            ['footer', 'Marketing Footer', false, '<div style="'.$wrap.';padding:20px 24px;text-align:center"><a href="/myaccount/rewards" style="display:inline-block;background:#00AEEF;color:#fff;padding:12px 22px;border-radius:6px;text-decoration:none;font-weight:700">See My Rewards</a></div>'
                .'<div style="'.$wrap.';border-top:1px solid #ddd;padding:14px 24px;font-size:12px;color:#666;text-align:center"><a href="https://facebook.com">Facebook</a> · <a href="https://instagram.com">Instagram</a> · <a href="/unsubscribe">Unsubscribe from offers</a><br>You get this because you opted in to offers from '.$brand.'.</div>'],
            ['footer', 'Legal Only', false, '<div style="'.$wrap.';border-top:1px solid #ddd;padding:14px 24px;font-size:12px;color:#666;text-align:center">'.$brand.' · PUCT Cert. No. XXXXX · <a href="/privacy-policy">Privacy</a></div>'],
        ];
        $ids = [];
        foreach ($parts as [$kind, $name, $default, $html]) {
            $ids[$name] = EmailPart::create(['kind' => $kind, 'name' => $name, 'is_default' => $default, 'html' => $html])->id;
        }
        $billing = EmailCategory::where('name', 'Billing')->value('id');
        $marketing = EmailCategory::where('name', 'Marketing')->value('id');
        EmailTemplate::where('email_category_id', $billing)->update(['email_header_id' => $ids['Billing Header'], 'email_footer_id' => $ids['Billing Footer']]);
        EmailTemplate::where('email_category_id', $marketing)->update(['email_footer_id' => $ids['Marketing Footer']]);
        EmailTemplate::where('name', 'like', '%Star%')->orWhere('name', 'like', '%Reward%')->update(['email_header_id' => $ids['Rewards Header'], 'email_footer_id' => $ids['Marketing Footer']]);
    }
}
