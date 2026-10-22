<?php

namespace Database\Seeders;

use App\Models\CookieConsent;
use App\Models\Customer;
use App\Models\SiteVisit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Sample cookie banner choices (fictional) from recent website visitors and a few signed-in customers. */
class CookieConsentSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(11);
        $visits = SiteVisit::where('blocked', false)->inRandomOrder()->limit(120)->get()->unique('visitor');
        $customers = Customer::whereNull('password')->orderBy('id')->limit(15)->get();   // not the test sign-ins, so they still see the banner
        $agents = ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15',
            'Mozilla/5.0 (Linux; Android 15; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36'];
        foreach ($visits->values() as $i => $v) {
            $roll = mt_rand(1, 100);
            [$choice, $a, $m] = $roll <= 62 ? ['all', true, true] : ($roll <= 85 ? ['necessary', false, false] : ['custom', true, (bool) mt_rand(0, 1)]);
            $k = CookieConsent::create(['visitor' => $v->visitor, 'customer_id' => $i < $customers->count() ? $customers[$i]->id : null, 'choice' => $choice,
                'analytics' => $a, 'marketing' => $m, 'policy_version' => CookieConsent::POLICY_VERSION, 'ip' => $v->ip, 'user_agent' => $agents[$i % count($agents)],
                'page' => '/'.ltrim($v->path === '/' ? '' : $v->path, '/'), 'created_at' => $v->created_at]);
            $k->update(['cookie_jar' => $this->jar($k, $i), 'cookies_seen_at' => $v->created_at->copy()->addMinutes(mt_rand(1, 90))]);
        }
    }

    /** A made-up cookie cache for the choice: the cookies it allows, now and then one it doesn't (set before they chose) or a stray. */
    private function jar(CookieConsent $k, int $i): array
    {
        $ts = $k->created_at->timestamp;
        $values = [
            'site_session' => 'eyJpdiI6…', 'XSRF-TOKEN' => 'eyJpdiI6…', 'et_vid' => $k->visitor,
            'cookie_consent' => json_encode(['v' => $k->policy_version, 'a' => (int) $k->analytics, 'm' => (int) $k->marketing]),
            '_ga' => 'GA1.1.'.mt_rand(100000000, 999999999).'.'.$ts, '_ga_ID' => 'GS1.1.'.$ts.'.'.mt_rand(1, 9).'.1.'.($ts + 300).'.0.0.0',
            '_clck' => Str::lower(Str::random(6)).'|2|fq'.mt_rand(1, 9).'|0|'.mt_rand(1700, 1800), '_fbp' => 'fb.1.'.($ts * 1000).'.'.mt_rand(100000000, 999999999),
            '_gcl_au' => '1.1.'.mt_rand(100000000, 999999999).'.'.$ts, 'li_fat_id' => Str::uuid()->toString(),
        ];
        $jar = array_intersect_key($values, array_flip($k->cookies()));
        if ($i % 9 === 4 && ! $k->analytics) {
            $jar['_ga'] = $values['_ga'];   // set before they chose
        }
        if ($i % 13 === 6) {
            $jar['wp-settings-time-1'] = (string) ($ts - 86400 * 400);   // left over from the old site
        }

        return $jar;
    }
}
