<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserSignIn;
use Illuminate\Database\Seeder;

/**
 * Sample sign-in history for the sample employees (My Profile → Signed In On): office and home browsers over the
 * last two months, how each ended, and one Log in as. IP addresses are from the ranges reserved for examples.
 */
class UserSignInSeeder extends Seeder
{
    private const BROWSERS = [
        ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36', '203.0.113.10'],   // office desktop
        ['Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15', '198.51.100.24'], // home laptop
        ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', '198.51.100.77'],
        ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:131.0) Gecko/20100101 Firefox/131.0', '203.0.113.10'],
    ];

    private const ENDED = ['Signed out', 'Signed out', 'Expired', 'Signed out', 'Expired', 'Signed in again'];

    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        foreach (User::where('email', 'like', '%@example.com')->orderBy('id')->get() as $i => $u) {
            $n = $u->email === 'admin@example.com' ? 24 : 12 + $i % 5;
            for ($k = 0; $k < $n; $k++) {
                [$ua, $ip] = self::BROWSERS[($k + $i) % (($k % 4 === 3) ? 4 : 2)];
                $at = now()->subDays(2 + intdiv($k * 60, $n))->setTime(8 + ($k + $i) % 10, ($k * 13 + $i * 7) % 60);
                UserSignIn::create(['user_id' => $u->id, 'ip' => $ip, 'user_agent' => $ua, 'how' => 'password', 'signed_in_at' => $at,
                    'ended_at' => $at->copy()->addMinutes(35 + ($k * 47) % 420), 'ended_reason' => self::ENDED[($k + $i) % count(self::ENDED)]]);
            }
            // An administrator looked at the admin as this person once
            if ($admin && $u->isNot($admin) && $i % 3 === 1) {
                $at = now()->subDays(9 + $i)->setTime(14, 5);
                UserSignIn::create(['user_id' => $u->id, 'impersonator_id' => $admin->id, 'ip' => '203.0.113.10', 'user_agent' => self::BROWSERS[0][0], 'how' => 'log_in_as',
                    'signed_in_at' => $at, 'ended_at' => $at->copy()->addMinutes(12), 'ended_reason' => 'Log in as ended']);
            }
        }
    }
}
