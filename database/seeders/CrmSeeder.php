<?php

namespace Database\Seeders;

use App\Models\CrmActivity;
use App\Models\CrmLead;
use App\Models\CrmPartner;
use App\Models\Customer;
use App\Models\Market;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** FICTIONAL CRM sample: partners and brokers, leads at every stage, and their calls, emails, meetings and follow-ups. */
class CrmSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(2468);
        $owners = User::where('active', true)->get()->filter(fn ($u) => $u->hasPerm('corral') && ! $u->company)->values();
        $pick = fn ($list) => $list[mt_rand(0, count($list) - 1)];
        $cities = ['Houston', 'Dallas', 'Fort Worth', 'Corpus Christi', 'Abilene', 'Odessa', 'McAllen', 'Lubbock', 'Tyler', 'Waco', 'Galveston', 'Denton'];
        $markets = Market::pluck('id')->all();

        $partners = collect([
            ['Bluebonnet Energy Brokers', 'Broker', 'Wren Calloway', 'kwh', 0.004, 'Dallas'],
            ['Gulf Coast Power Advisors', 'Broker', 'Lane Pritchard', 'kwh', 0.0035, 'Houston'],
            ['Panhandle Commercial Energy', 'Broker', 'Remy Okafor', 'kwh', 0.003, 'Lubbock'],
            ['Pecan Street Property Group', 'Property Manager', 'Shea Vandermolen', 'flat', 0.05, 'Fort Worth'],
            ['Live Oak Homes', 'Home Builder', 'Marlo Quintana', 'flat', 0.04, 'Tyler'],
            ['Cedar Ridge Realty', 'Realtor', 'Toni Abernathy', 'flat', 0.03, 'Waco'],
            ['Lone Star Small Business Assn. (sample)', 'Referral Partner', 'Jules Harrow', 'flat', 0.02, 'Corpus Christi'],
            ['Brazos Valley Facilities Co.', 'Broker', 'Kendall Ashby', 'kwh', 0.0045, 'Denton', 'Ended'],
        ])->map(fn ($p) => CrmPartner::create(['name' => $p[0], 'kind' => $p[1], 'contact_name' => $p[2], 'commission_basis' => $p[3], 'commission_rate' => $p[4], 'city' => $p[5],
            'email' => strtolower(explode(' ', $p[2])[0]).'@'.str($p[0])->slug()->limit(18, '').'.example.com', 'phone' => '(555) 555-0'.mt_rand(200, 299),
            'status' => $p[6] ?? 'Active', 'owner_id' => $owners[mt_rand(0, $owners->count() - 1)]->id,
            'agreement_ends_on' => ($p[6] ?? null) === 'Ended' ? today()->subMonths(2) : today()->addMonths(mt_rand(4, 22)),
            'notes' => $p[1] === 'Broker' ? 'Signed broker agreement on file. Pays monthly on flowing kWh.' : 'Referral agreement; paid quarterly.']));

        $first = ['Avery', 'Blake', 'Carmen', 'Dana', 'Eden', 'Frankie', 'Gale', 'Hollis', 'Ira', 'Jamie', 'Kerry', 'Lennon', 'Micah', 'Noel', 'Oakley', 'Peyton', 'Rowan', 'Sage', 'Tatum', 'Val'];
        $last = ['Example', 'Sampleford', 'Demopoulos', 'Testerman', 'Mockley', 'Fakeworth', 'Placeholder', 'Specimen', 'Fictionelli', 'Notreal'];
        $biz = ['Ranch Supply', 'Taqueria', 'Auto Body', 'Dental Group', 'Veterinary Clinic', 'Self Storage', 'Laundromat', 'Bakery', 'Fitness Studio', 'Machine Shop', 'Church', 'Daycare', 'Hardware', 'Car Wash', 'Cold Storage', 'Print Shop', 'Brewing Co.', 'Feed Store'];
        $towns = ['Bluebonnet', 'Caliche', 'Mesquite Flat', 'Pecan Hollow', 'Red River', 'Sandy Creek', 'Twin Oaks', 'Prairie View', 'Cottonwood', 'Hill Country'];
        $providers = ['Other REP A (sample)', 'Other REP B (sample)', 'Other REP C (sample)', 'Month-to-month default'];
        $stagePlan = array_merge(array_fill(0, 12, 'new'), array_fill(0, 11, 'contacted'), array_fill(0, 10, 'qualified'), array_fill(0, 8, 'proposal'), array_fill(0, 14, 'won'), array_fill(0, 9, 'lost'));
        $customers = Customer::where('status', 'Good - On Flow')->pluck('id')->shuffle()->values();
        $cust = 0;

        foreach ($stagePlan as $i => $stage) {
            $kind = $pick(['Residential', 'Small Business', 'Small Business', 'Small Business', 'Commercial', 'Commercial', 'Multi-site']);
            $name = $pick($first).' '.$pick($last);
            $company = $kind === 'Residential' ? null : $pick($towns).' '.$pick($biz);
            $sites = $kind === 'Multi-site' ? mt_rand(3, 24) : 1;
            $kwh = match ($kind) {
                'Residential' => mt_rand(9, 22) * 1000, 'Small Business' => mt_rand(25, 140) * 1000, 'Commercial' => mt_rand(180, 900) * 1000, default => $sites * mt_rand(40, 160) * 1000
            };
            $source = $pick(['Website', 'Website', 'Referral', 'Broker', 'Broker', 'Partner', 'Event', 'Outbound', 'Renewal']);
            $partner = in_array($source, ['Broker', 'Partner'], true) ? $partners->filter(fn ($p) => ($source === 'Broker') === ($p->kind === 'Broker'))->random() : null;
            $created = now()->subDays(mt_rand(3, 150))->setTime(mt_rand(8, 17), mt_rand(0, 59));
            $closed = in_array($stage, ['won', 'lost'], true) ? $created->copy()->addDays(mt_rand(7, 60))->min(now()->subHour()) : null;
            $owner = $owners[mt_rand(0, $owners->count() - 1)];
            $lead = CrmLead::create([
                'name' => $name, 'company' => $company, 'title' => $company ? $pick(['Owner', 'Office Manager', 'Operations Manager', 'Facilities Director', 'CFO']) : null,
                'kind' => $kind, 'email' => strtolower(str_replace(' ', '.', $name)).'@'.($company ? str($company)->slug()->limit(16, '') : 'mail').'.example.com',
                'phone' => '(555) 555-'.str_pad((string) mt_rand(300, 9999), 4, '0', STR_PAD_LEFT), 'city' => $pick($cities), 'market_id' => $markets ? $pick($markets) : null,
                'source' => $source, 'partner_id' => $partner?->id, 'stage' => $stage, 'annual_kwh' => $kwh, 'est_value' => round($kwh * (mt_rand(118, 152) / 1000), 2), 'sites' => $sites,
                'current_provider' => $pick($providers), 'contract_ends_on' => today()->addDays(mt_rand(-30, 300)), 'owner_id' => $owner->id,
                'next_follow_up_on' => $closed ? null : today()->addDays($i % 4 === 0 ? 0 : mt_rand(-6, 14)),   // every 4th one is due today 'stage_changed_at' => $closed ?? $created->copy()->addDays(mt_rand(0, 10))->min(now()),
                'closed_at' => $closed, 'lost_reason' => $stage === 'lost' ? $pick(CrmLead::LOST_REASONS) : null,
                'customer_id' => $stage === 'won' && $cust < $customers->count() && mt_rand(0, 1) ? $customers[$cust++] : null,
                'notes' => $company ? $pick(['Wants a fixed rate through the summer.', 'Comparing three quotes; price is the deciding factor.', 'Asked about green energy options.', 'Has two meters on the same property.', null]) : null,
            ]);
            $lead->forceFill(['created_at' => $created, 'updated_at' => $closed ?? $created])->saveQuietly();
            $this->history($lead, $owner, $created, $closed);
        }

        // Everyone who owns leads has one follow-up due today and one overdue, so "Mine" is never empty
        $open = CrmLead::open()->orderBy('id')->get()->values();
        foreach ($owners as $k => $o) {
            foreach ([0, -mt_rand(1, 5)] as $j => $days) {
                $lead = $open[($k * 2 + $j) % $open->count()];
                $lead->update(['owner_id' => $o->id, 'next_follow_up_on' => today()->addDays($days)]);
                $task = $lead->activities()->where('type', 'task')->whereNull('done_at')->orderBy('due_on')->first();
                $task?->update(['due_on' => today()->addDays($days), 'user_id' => $o->id]);
            }
        }

        // Partner check-ins and follow-ups
        foreach ($partners as $p) {
            foreach (range(1, mt_rand(2, 4)) as $k) {
                $at = now()->subDays(mt_rand(2, 90));
                CrmActivity::create(['partner_id' => $p->id, 'user_id' => $p->owner_id, 'type' => $pick(['call', 'email', 'meeting']), 'subject' => $pick(['Monthly broker check-in', 'Sent updated commercial rate sheet', 'Reviewed commission statement', 'Lunch to talk about new referrals']),
                    'done_at' => $at, 'created_at' => $at, 'updated_at' => $at]);
            }
            if ($p->status === 'Active') {
                CrmActivity::create(['partner_id' => $p->id, 'user_id' => $p->owner_id, 'type' => 'task', 'subject' => 'Quarterly review with '.$p->contact_name, 'due_on' => today()->addDays(mt_rand(-3, 20)), 'created_at' => now()->subDays(5)]);
            }
        }
    }

    /** A believable trail of calls, emails, meetings and stage changes for one lead. */
    private function history(CrmLead $lead, User $owner, Carbon $created, ?Carbon $closed): void
    {
        $order = ['new' => 0, 'contacted' => 1, 'qualified' => 2, 'proposal' => 3, 'won' => 4, 'lost' => mt_rand(1, 3)];
        $labels = ['New', 'Contacted', 'Qualified', 'Proposal Sent'];
        $steps = [
            ['call', 'Intro call', 'Walked through our fixed-rate plans and asked for a recent bill.'],
            ['email', 'Sent bill upload link', null],
            ['meeting', 'Site visit and usage review', 'Looked at 12 months of usage; peak in July and August.'],
            ['email', 'Sent proposal', 'Quoted 24 and 36-month fixed rates with no deposit for good credit.'],
        ];
        $add = fn ($type, $subject, $body, $at, $extra = []) => CrmActivity::create(['lead_id' => $lead->id, 'user_id' => $owner->id, 'type' => $type, 'subject' => $subject, 'body' => $body,
            'done_at' => $extra['done_at'] ?? ($type === 'task' ? null : $at), 'due_on' => $extra['due_on'] ?? null, 'created_at' => $at, 'updated_at' => $at]);

        $add('note', 'Lead added from '.$lead->source, $lead->partner ? 'Sent by '.$lead->partner->name.'.' : null, $created);
        $at = $created->copy();
        for ($s = 0; $s < $order[$lead->stage] && $s < 4; $s++) {
            $at = $at->copy()->addDays(mt_rand(1, 6))->setTime(mt_rand(8, 17), mt_rand(0, 59));
            if ($at->isFuture()) {
                break;
            }
            [$type, $subject, $body] = $steps[$s];
            $add($type, $subject, $body, $at);
            if ($s + 1 < 4) {
                $add('stage', $labels[$s].' → '.$labels[$s + 1], null, $at->copy()->addMinutes(5));
            }
        }
        if ($closed) {
            $add('stage', ($labels[min(3, $order[$lead->stage])] ?? 'Proposal Sent').' → '.$lead->stageLabel(), $lead->lost_reason, $closed);
            if ($lead->stage === 'won') {
                $add('call', 'Welcome call: confirmed start date and AutoPay', null, $closed->copy()->addDay()->min(now()));
            }
            // A follow-up that was done along the way
            $add('task', 'Call back about the quote', null, $closed->copy()->subDays(3), ['due_on' => $closed->copy()->subDay()->toDateString(), 'done_at' => $closed->copy()->subDay()]);
        } else {
            $add('task', ['Call back about the quote', 'Check if they got the proposal', 'Ask for the latest bill', 'Confirm the decision date', 'Send the 36-month option'][mt_rand(0, 4)],
                null, now()->subDays(mt_rand(1, 6)), ['due_on' => $lead->next_follow_up_on?->toDateString()]);
            // One already done in the last two weeks
            $done = now()->subDays(mt_rand(1, 12))->setTime(mt_rand(9, 17), mt_rand(0, 59));
            if ($done->gt($created)) {
                $add('task', ['Send rate sheet', 'Confirm the meter number', 'Call about the bill they sent'][mt_rand(0, 2)], null, $done->copy()->subDays(2), ['due_on' => $done->toDateString(), 'done_at' => $done]);
            }
            if (mt_rand(0, 2) === 0) {
                $add('task', 'Visit before their contract ends', null, now()->subDays(2), ['due_on' => today()->addDays(mt_rand(15, 40))->toDateString()]);
            }
        }
    }
}
