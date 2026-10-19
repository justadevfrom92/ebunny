<?php

namespace Database\Seeders;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\Market;
use App\Models\Note;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Database\Seeder;

/** FICTIONAL customers with payments, bills, notes and exception-queue items. */
class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $markets = Market::pluck('id', 'name');
        $plans = Plan::pluck('id', 'internal');
        $admin = User::where('email', 'admin@example.com')->first();

        foreach (DatabaseSeeder::data('customers') as $c) {
            $customer = Customer::create([
                'account' => $c['account'], 'ticket' => $c['ticket'], 'name' => $c['name'], 'type' => $c['type'],
                'phone' => $c['phone'], 'email' => $c['email'], 'address' => $c['address'], 'city' => $c['city'],
                'zip' => $c['zip'], 'market_id' => $markets[$c['market']] ?? null, 'esiid' => $c['esiid'],
                'plan_id' => $plans[$c['planCode']] ?? null, 'status' => $c['status'], 'exception' => $c['exception'] ?: null,
                'source' => $c['source'], 'balance' => $c['balance'], 'autopay' => $c['autopay'],
                'paperless' => $c['paperless'], 'stars' => $c['stars'],
            ]);
            $customer->forceFill(['created_at' => $c['created'].' 09:14:00', 'updated_at' => $c['created'].' 09:14:00'])->save();
            if ($c['bookmarked'] && $admin) {
                $admin->bookmarks()->attach($customer->id);
            }
        }

        $customers = Customer::pluck('id', 'account');
        foreach (DatabaseSeeder::data('customer_activity') as $a) {
            $id = $customers[$a['account']];
            // Sample activity never runs past today
            foreach ($a['payments'] as $p) {
                if ($p['date'] > today()->toDateString()) {
                    continue;
                }
                Payment::create(['customer_id' => $id, 'reference' => $p['id'], 'paid_on' => $p['date'], 'amount' => $p['amount'], 'method' => $p['method'], 'source' => $p['source'], 'status' => $p['status']]);
            }
            foreach ($a['bills'] as $b) {
                if ($b['date'] > today()->toDateString()) {
                    continue;
                }
                Bill::create(['customer_id' => $id, 'reference' => $b['id'], 'billed_on' => $b['date'], 'kwh' => $b['kwh'], 'amount' => $b['amount']]);
            }
            foreach ($a['notes'] as $n) {
                $note = Note::create(['customer_id' => $id, 'author' => $n['by'], 'body' => $n['text']]);
                $note->forceFill(['created_at' => $n['at'].':00', 'updated_at' => $n['at'].':00'])->save();
            }
        }

        // Spread open items across the exception queues
        $ids = $customers->values();
        $keys = array_keys(config('admin.queues'));
        foreach (DatabaseSeeder::data('queues') as $i => [$name, $count]) {
            for ($k = 0; $k < $count; $k++) {
                WorkItem::create([
                    'queue' => $keys[$i], 'customer_id' => $ids[($i + $k) % $ids->count()],
                    'summary' => $name.' — needs review', 'created_at' => now()->subDays($k + 1),
                ]);
            }
            // History: items people already fixed over the last two months
            $fixers = User::whereIn('email', ['csr@example.com', 'lead@example.com', 'admin@example.com', 'finance@example.com'])->pluck('id')->all();
            for ($k = 0; $k < 12 + ($i * 7) % 20; $k++) {
                $at = now()->subDays(2 + ($k * 3 + $i) % 60)->setTime(8 + $k % 9, ($k * 13) % 60);
                WorkItem::create([
                    'queue' => $keys[$i], 'customer_id' => $ids[($i * 3 + $k) % $ids->count()], 'summary' => $name.' — needs review',
                    'created_at' => $at, 'resolved_at' => $at->copy()->addHours(1 + ($k * 5) % 70), 'resolved_by' => $fixers ? $fixers[$k % count($fixers)] : null,
                ]);
            }
        }
    }
}
