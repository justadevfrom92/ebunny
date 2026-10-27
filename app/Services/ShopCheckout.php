<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\ShopOrder;
use App\Models\StarEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Places a Shop order for an account, the same way whether the customer checks out in My Account or an employee
 * orders for them in Corral by phone: protection plans go on the account, Rewards stars are credited, and a note
 * goes on the account.
 */
class ShopCheckout
{
    /**
     * @param  array  $summary  Cart::summarize(...)
     * @param  array{pay_with: string, method: ?int, ship_to: array}  $pay
     */
    public static function place(Customer $c, array $summary, array $pay, ?User $by = null): ShopOrder
    {
        return DB::transaction(function () use ($c, $summary, $pay, $by) {
            $order = ShopOrder::create([
                'number' => 'SO-'.(100000 + (int) ShopOrder::max('id') + 1),
                'customer_id' => $c->id,
                'items' => $summary['lines']->map(fn ($l) => ['sku' => $l['sku'], 'name' => $l['name'], 'qty' => $l['qty'], 'price' => $l['price'], 'line' => $l['line'], 'monthly' => $l['monthly'], 'stars' => $l['stars_total']])->all(),
                'subtotal' => $summary['subtotal'], 'tax' => $summary['tax'], 'shipping' => $summary['shipping'], 'total' => $summary['total'], 'monthly' => $summary['monthly'],
                'stars' => $summary['stars'], 'pay_with' => $pay['pay_with'], 'payment_method_id' => $pay['pay_with'] === 'card' ? $pay['method'] : null,
                'ship_to' => $pay['ship_to'], 'payment_status' => $pay['pay_with'] === 'bill' ? 'On next bill' : 'Pending', 'placed_by' => $by?->id,
            ]);
            foreach ($summary['lines']->where('monthly', true) as $l) {
                Products::add($c, Cart::PROTECTION[$l['sku']], $by);
            }
            if ($summary['stars'] > 0) {
                StarEntry::create(['customer_id' => $c->id, 'reason' => 'Shop order '.$order->number, 'stars' => $summary['stars']]);
                $c->syncStars();
            }
            $c->notes()->create(['user_id' => $by?->id, 'author' => $by ? $by->name : 'Customer (MyAccount)', 'category' => 'Shop', 'action' => 'Shop Order', 'priority' => 'Low',
                'body' => ($by ? 'Placed shop order '.$order->number.' for the customer by phone' : 'Placed shop order '.$order->number).' for $'.number_format($order->total, 2)
                    .($order->monthly > 0 ? ' plus $'.number_format($order->monthly, 2).'/mo in protection plans' : '').'.']);

            return $order;
        });
    }

    /** Ship to the service address, or another address. */
    public static function shipTo(Customer $c, array $data): array
    {
        return ($data['ship'] ?? 'service') === 'other'
            ? ['name' => $c->name, 'street' => $data['street'], 'city' => $data['city'], 'state' => 'TX', 'zip' => $data['zip']]
            : ['name' => $c->name, 'street' => trim($c->address.($c->unit ? ' #'.$c->unit : '')), 'city' => $c->city, 'state' => 'TX', 'zip' => $c->zip];
    }
}
