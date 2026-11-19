<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\StarEntry;
use App\Models\User;
use App\Services\Cart;
use App\Services\ShopCheckout;
use Illuminate\Database\Seeder;

/**
 * Sample Shop orders (fictional): placed the same way as real ones (ShopCheckout), so totals, tax, Rewards stars,
 * protection plans on the account and the account note all match. Some by the customer in My Account, some by an
 * employee on the phone, paid by card or on the next bill, in every status.
 */
class ShopOrderSeeder extends Seeder
{
    public function run(): void
    {
        $csr = User::where('email', 'csr@example.com')->first();
        $lead = User::where('email', 'lead@example.com')->first();
        // [account (null = next customer in the list), items, days ago, status, by]
        $orders = [
            ['1219016476', ['TH-100' => 1, 'LT-300' => 4], 3, 'Placed', null],
            ['1219016476', ['PR-500' => 1, 'SO-600' => 1], 41, 'Delivered', $csr],
            [null, ['EV-200' => 1], 1, 'Placed', null],
            [null, ['TH-110' => 2], 6, 'Shipped', $csr],
            [null, ['LT-310' => 6, 'PL-400' => 2], 9, 'Shipped', null],
            [null, ['GR-700' => 1, 'PR-510' => 1], 14, 'Delivered', $lead],
            [null, ['SO-610' => 1], 18, 'Delivered', null],
            [null, ['TH-120' => 1, 'PL-410' => 1], 22, 'Canceled', null],
            [null, ['PR-520' => 1], 27, 'Delivered', $csr],
            [null, ['LT-320' => 2, 'LT-330' => 2], 33, 'Delivered', null],
            [null, ['EV-210' => 1, 'PR-530' => 1], 38, 'Delivered', $lead],
            [null, ['GR-710' => 1], 47, 'Delivered', null],
            [null, ['TH-140' => 1], 55, 'Canceled', $csr],
            [null, ['PL-420' => 3, 'SO-620' => 1], 63, 'Delivered', null],
        ];
        usort($orders, fn ($a, $b) => $b[2] <=> $a[2]);   // oldest first, so order numbers go up with the date
        $others = Customer::where('account', '!=', '1219016476')->orderBy('id')->get()->values();
        $next = 0;
        foreach ($orders as [$account, $items, $daysAgo, $status, $by]) {
            $c = $account ? Customer::where('account', $account)->first() : $others[$next++ % $others->count()];
            $summary = Cart::summarize($items);
            if (! $c || $summary['lines']->isEmpty() || $summary['unavailable']->isNotEmpty()) {
                continue;
            }
            $card = PaymentMethod::where('customer_id', $c->id)->where('type', 'Credit Card')->whereNull('removed_at')->first();
            // Protection plans only (nothing due today) always go on the bill
            $payWith = $card && $summary['total'] > 0 && $next % 3 !== 0 ? 'card' : 'bill';
            $order = ShopCheckout::place($c, $summary, ['pay_with' => $payWith, 'method' => $card?->id, 'ship_to' => ShopCheckout::shipTo($c, [])], $by);

            // Back-date it, and what checkout recorded with it
            $at = now()->subDays($daysAgo)->setTime(9 + $daysAgo % 9, ($daysAgo * 7) % 60);
            $paid = $payWith === 'card' ? ($status === 'Canceled' ? 'Refunded' : ($status === 'Placed' ? 'Pending' : 'Paid')) : ($status === 'Canceled' ? 'Canceled' : 'On next bill');
            $order->forceFill(['status' => $status, 'payment_status' => $paid, 'created_at' => $at, 'updated_at' => $status === 'Placed' ? $at : $at->copy()->addDays(min(3, $daysAgo))])->save();
            $c->notes()->where('body', 'like', '%'.$order->number.'%')->update(['created_at' => $at, 'updated_at' => $at]);
            StarEntry::where('customer_id', $c->id)->where('reason', 'Shop order '.$order->number)->update(['created_at' => $at, 'updated_at' => $at]);
        }
    }
}
