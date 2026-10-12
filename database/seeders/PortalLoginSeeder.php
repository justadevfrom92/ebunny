<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

/** My Account logins for a few sample customers in different situations, for the test sign-ins (password "password"). The business login has several properties. */
class PortalLoginSeeder extends Seeder
{
    public function run(): void
    {
        $taken = Customer::whereNotNull('password')->pluck('id');
        foreach ([
            'test.business' => fn ($q) => $q->where('type', 'Small Business'),
            'test.pastdue' => fn ($q) => $q->where('balance', '>', 50)->where('status', 'Good - On Flow'),
            'test.credit' => fn ($q) => $q->where('balance', '<', 0),
            'test.deposit' => fn ($q) => $q->where('status', 'Pending - Deposit Due'),
            'test.moved' => fn ($q) => $q->whereIn('status', ['Dropped - Churned', 'Moved Out', 'Disconnected']),
        ] as $username => $where) {
            $c = $where(Customer::whereNotIn('id', $taken)->whereNotNull('email'))->orderBy('id')->first();
            if ($c) {
                $c->forceFill(['username' => $username, 'password' => 'password'])->saveQuietly();
                $taken->push($c->id);
            }
        }

        // The business login has more locations: each is its own account (property) under the same login
        $business = Customer::where('username', 'test.business')->first();
        if ($business) {
            $more = Customer::where('type', 'Small Business')->whereNotIn('id', $taken)->whereNull('password')->orderBy('id')->limit(3)->get();
            foreach ($more as $p) {
                $p->forceFill(['name' => $business->name, 'email' => $business->email, 'billing_street' => $business->billing_street ?: $business->address,
                    'billing_city' => $business->billing_city ?: $business->city, 'billing_zip' => $business->billing_zip ?: $business->zip])->saveQuietly();
            }
            $business->forceFill(['linked_accounts' => $more->pluck('account')->all()])->saveQuietly();
        }
    }
}
