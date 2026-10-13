<?php

namespace App\Services;

use App\Http\Controllers\Frontend\ShopController;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * The Shop cart, kept in the visitor's session (so it survives signing in to My Account).
 * Protection plans are added to the electricity bill monthly instead of charged today.
 */
class Cart
{
    private const KEY = 'shop_cart';

    public const TAX = 0.0825;

    /** Shop items that are protection plans → their product name in My Account → Products & Protections. */
    public const PROTECTION = ['PR-500' => 'Surge Protection Program', 'PR-510' => 'Air Conditioning Protection Program',
        'PR-520' => 'Electric System Protection Program', 'PR-530' => 'Electric Vehicle Charger Protection Program'];

    public function items(): array
    {
        return session(self::KEY, []);
    }

    public function count(): int
    {
        return (int) array_sum($this->items());
    }

    public function set(string $sku, int $qty): void
    {
        $sku = strtoupper($sku);
        $items = $this->items();
        $max = isset(self::PROTECTION[$sku]) ? 1 : 10;
        $qty <= 0 ? Arr::forget($items, $sku) : $items[$sku] = min($max, $qty);
        session([self::KEY => $items]);
    }

    public function add(string $sku, int $qty = 1): void
    {
        $this->set($sku, ($this->items()[strtoupper($sku)] ?? 0) + max(1, $qty));
    }

    public function clear(): void
    {
        session()->forget(self::KEY);
    }

    /** Cart lines with product details, and the totals: due today (goods + tax) and added to the monthly bill (protection plans). */
    public function summary(): array
    {
        $products = ShopController::products()->keyBy('sku');
        $lines = collect($this->items())->map(function ($qty, $sku) use ($products) {
            $p = $products->get($sku);
            if (! $p) {
                return null;
            }
            $monthly = isset(self::PROTECTION[$sku]);

            return $p + ['qty' => $qty, 'monthly' => $monthly, 'line' => round($monthly ? $p['price'] / 12 : $p['price'] * $qty, 2), 'stars_total' => $p['stars'] * $qty];
        })->filter()->values();
        $goods = $lines->where('monthly', false);
        $subtotal = round($goods->sum('line'), 2);
        $tax = round($subtotal * self::TAX, 2);

        return ['lines' => $lines, 'subtotal' => $subtotal, 'tax' => $tax, 'shipping' => 0.0, 'total' => round($subtotal + $tax, 2),
            'monthly' => round($lines->where('monthly', true)->sum('line'), 2), 'stars' => (int) $lines->sum('stars_total'), 'count' => (int) $lines->sum('qty'),
            'unavailable' => $lines->where('stock', false)->values()];
    }

    public function lines(): Collection
    {
        return $this->summary()['lines'];
    }
}
