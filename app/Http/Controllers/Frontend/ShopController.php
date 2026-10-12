<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/** The website's Shop (/shop): a sample product catalog with filters, sorts and paging. Works without JavaScript. */
class ShopController extends Controller
{
    public const SORTS = ['featured' => 'Featured', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'rating' => 'Top rated', 'reviews' => 'Most reviewed', 'name' => 'Name A–Z'];

    public function index(Request $request): View
    {
        $cats = config('shop.categories');
        $f = $request->validate([
            'cat' => ['nullable', 'array'], 'cat.*' => ['string', 'in:'.implode(',', array_keys($cats))],
            'min' => ['nullable', 'numeric', 'min:0'], 'max' => ['nullable', 'numeric', 'min:0'],
            'rating' => ['nullable', 'in:4,4.5'], 'stock' => ['nullable', 'boolean'], 'sale' => ['nullable', 'boolean'], 'stars' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:60'], 'sort' => ['nullable', 'in:'.implode(',', array_keys(self::SORTS))], 'per' => ['nullable', 'in:12,24'],
        ]);
        $all = collect(config('shop.products'))->map(fn ($p, $i) => array_combine(['sku', 'name', 'cat', 'price', 'was', 'rating', 'reviews', 'stars', 'stock', 'badge', 'icon', 'blurb'], $p) + ['order' => $i]);
        $q = mb_strtolower(trim((string) ($f['q'] ?? '')));

        // Filter on everything but category to count each category's matches (so the counts beside the checkboxes are honest)
        $base = $all
            ->when($q !== '', fn ($c) => $c->filter(fn ($p) => str_contains(mb_strtolower($p['name'].' '.$p['blurb']), $q)))
            ->when(isset($f['min']), fn ($c) => $c->where('price', '>=', (float) $f['min']))
            ->when(isset($f['max']), fn ($c) => $c->where('price', '<=', (float) $f['max']))
            ->when($f['rating'] ?? null, fn ($c, $r) => $c->where('rating', '>=', (float) $r))
            ->when($f['stock'] ?? false, fn ($c) => $c->where('stock', true))
            ->when($f['sale'] ?? false, fn ($c) => $c->filter(fn ($p) => $p['was'] !== null))
            ->when($f['stars'] ?? false, fn ($c) => $c->where('stars', '>', 0));
        $counts = $base->countBy('cat');
        $list = $base->when($f['cat'] ?? null, fn ($c, $sel) => $c->whereIn('cat', $sel));

        $sort = $f['sort'] ?? 'featured';
        $list = match ($sort) {
            'price_asc' => $list->sortBy([['price', 'asc'], ['name', 'asc']]),
            'price_desc' => $list->sortBy([['price', 'desc'], ['name', 'asc']]),
            'rating' => $list->sortBy([['rating', 'desc'], ['reviews', 'desc']]),
            'reviews' => $list->sortByDesc('reviews'),
            'name' => $list->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE),
            default => $list->sortBy([[fn ($p) => $p['badge'] ? 0 : 1, 'asc'], ['order', 'asc']]),
        };
        $per = (int) ($f['per'] ?? 12);
        $page = max(1, $request->integer('page', 1));
        $items = new LengthAwarePaginator($list->values()->forPage($page, $per), $list->count(), $per, $page, ['path' => route('shop'), 'query' => $request->except('page')]);

        return view('frontend.shop', ['items' => $items, 'f' => $f, 'cats' => $cats, 'counts' => $counts, 'sort' => $sort, 'sorts' => self::SORTS,
            'priceTop' => (int) ceil($all->max('price') / 50) * 50]);
    }
}
