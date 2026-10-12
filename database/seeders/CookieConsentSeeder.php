<?php

namespace Database\Seeders;

use App\Models\CookieConsent;
use App\Models\Customer;
use App\Models\SiteVisit;
use Illuminate\Database\Seeder;

/** Sample cookie banner choices (fictional) from recent website visitors and a few signed-in customers. */
class CookieConsentSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(11);
        $visits = SiteVisit::where('blocked', false)->inRandomOrder()->limit(120)->get()->unique('visitor');
        $customers = Customer::whereNull('password')->orderBy('id')->limit(15)->get();   // not the test sign-ins, so they still see the banner
        foreach ($visits->values() as $i => $v) {
            $roll = mt_rand(1, 100);
            [$choice, $a, $m] = $roll <= 62 ? ['all', true, true] : ($roll <= 85 ? ['necessary', false, false] : ['custom', true, (bool) mt_rand(0, 1)]);
            CookieConsent::create(['visitor' => $v->visitor, 'customer_id' => $i < $customers->count() ? $customers[$i]->id : null, 'choice' => $choice,
                'analytics' => $a, 'marketing' => $m, 'policy_version' => CookieConsent::POLICY_VERSION, 'ip' => $v->ip, 'user_agent' => 'Mozilla/5.0 (sample)',
                'page' => '/'.ltrim($v->path === '/' ? '' : $v->path, '/'), 'created_at' => $v->created_at]);
        }
    }
}
