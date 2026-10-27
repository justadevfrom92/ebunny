<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\ShopProduct;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** The website's Shop (/shop): a sample product catalog with filters, sorts and paging, and a page per product. Works without JavaScript. */
class ShopController extends Controller
{
    public const SORTS = ['featured' => 'Featured', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'rating' => 'Top rated', 'reviews' => 'Most reviewed', 'name' => 'Name A–Z'];

    /** Every product, keyed by its page address (/shop/th-100). */
    public static function products(): Collection
    {
        // Products are kept in the database (Corral → Products); only active ones are in the shop
        return ShopProduct::where('active', true)->orderBy('position')->orderBy('id')->get()->map->toShop()->keyBy('slug');
    }

    /** One product's own page: photo area, price, what's included, specs, ratings and related products. */
    public function show(string $slug): View
    {
        $all = self::products();
        $p = $all->get(strtolower($slug)) ?? abort(404);
        // Star breakdown for the ratings bars, spread around the average (sample data)
        $r = $p['rating'];
        $split = [5 => max(0, ($r - 4) * 0.9 + 0.1), 4 => 0.25, 3 => max(0.02, (5 - $r) * 0.12), 2 => max(0.01, (5 - $r) * 0.06), 1 => max(0.01, (5 - $r) * 0.05)];
        $total = array_sum($split);
        $breakdown = collect($split)->map(fn ($v) => (int) round($p['reviews'] * $v / $total));

        return view('frontend.shop.show', [
            'p' => $p, 'cats' => config('shop.categories'), 'details' => config('shop.details.'.$p['cat']), 'breakdown' => $breakdown,
            'related' => $all->where('cat', $p['cat'])->where('slug', '!=', $p['slug'])->take(4)
                ->concat($all->where('cat', '!=', $p['cat'])->where('badge', '!=', null)->take(4))->take(4),
        ]);
    }

    public function index(Request $request): View
    {
        $cats = config('shop.categories');
        $f = $request->validate([
            'cat' => ['nullable', 'array'], 'cat.*' => ['string', 'in:'.implode(',', array_keys($cats))],
            'min' => ['nullable', 'numeric', 'min:0'], 'max' => ['nullable', 'numeric', 'min:0'],
            'rating' => ['nullable', 'in:4,4.5'], 'stock' => ['nullable', 'boolean'], 'sale' => ['nullable', 'boolean'], 'stars' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:60'], 'sort' => ['nullable', 'in:'.implode(',', array_keys(self::SORTS))], 'per' => ['nullable', 'in:12,24'],
        ]);
        $all = self::products();
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

        return view('frontend.shop.index', ['items' => $items, 'f' => $f, 'cats' => $cats, 'counts' => $counts, 'sort' => $sort, 'sorts' => self::SORTS,
            'priceTop' => (int) ceil($all->max('price') / 50) * 50]);
    }
}
