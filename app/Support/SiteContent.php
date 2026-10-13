<?php

namespace App\Support;

use App\Models\ContentBlock;
use App\Models\Market;
use App\Models\Page;
use App\Models\Site;
use App\Services\Catalog;

/**
 * Turns Lando content into website HTML: expands [[block|slug]] (or
 * [[block|id=N]]) codes, filling in the block's {{variables}} from the code
 * ([[block|slug|city=Dallas]]) or the page component, and collects each zone's page components.
 * Content is written by signed-in Lando editors and is output as HTML.
 */
class SiteContent
{
    /** Heading for [[zip_form]] on the page being rendered (Address Box Title). */
    public static ?string $addressBoxTitle = null;

    /** [[block|slug]] or [[block|id=N]], optionally passing the block's variables: [[block|slug|city=Dallas|utility=Oncor]] */
    private const CODE = '/\[\[block\|(?:id=(\d+)|([A-Za-z0-9_-]+))((?:\|[A-Za-z0-9_]+=[^|\]]*)*)\]\]/';

    /** "|city=Dallas|utility=Oncor" → ['city' => 'Dallas', 'utility' => 'Oncor'] */
    private static function params(string $s): array
    {
        $out = [];
        foreach (array_filter(explode('|', $s)) as $pair) {
            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
            $out[$k] = html_entity_decode(trim($v));
        }

        return $out;
    }

    public static function expand(?string $html, ?Site $site, int $depth = 0): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $html = self::widgets($html, $site);

        $out = preg_replace_callback(self::CODE, function ($m) use ($site, $depth) {
            if ($depth >= 3) {
                return ''; // a block that includes itself
            }
            $block = ContentBlock::query()
                ->when($m[1] !== '', fn ($q) => $q->whereKey((int) $m[1]), fn ($q) => $q->where('slug', $m[2]))
                ->where(fn ($q) => $q->whereNull('site_id')->orWhere('site_id', $site?->id))
                ->first();

            return $block ? self::expand($block->withVars(self::params($m[3] ?? '')), $site, $depth + 1) : '';
        }, $html);

        // Codes this site doesn't support (e.g. old checkout codes) are left out rather than shown
        return $depth === 0 ? preg_replace('/\[\[[a-z_]+(\|[^\]]*)?\]\]/', '', $out) : $out;
    }

    /**
     * Built-in widgets editors can drop into content or blocks:
     * [[year]], [[phone]], [[zip_form]], [[plans|group=featured|limit=3]].
     * Anything else in [[…]] that isn't a block is left out of the page.
     */
    private static function widgets(string $html, ?Site $site): string
    {
        return preg_replace_callback('/\[\[(year|phone|zip_form|plans)((?:\|[a-z_]+=[A-Za-z0-9_-]+)*)\]\]/', function ($m) use ($site) {
            parse_str(str_replace('|', '&', ltrim($m[2], '|')), $opt);

            return match ($m[1]) {
                'year' => now()->format('Y'),
                'phone' => e($site?->phone ?: config('brand.phone')),
                'zip_form' => view('frontend.widgets.zip-form', ['title' => self::$addressBoxTitle ?: $site?->address_box_title ?: 'Find your plan'])->render(),
                'plans' => '<div class="plan-grid" id="plan-grid" data-group="'.e($opt['group'] ?? 'resi').'"'
                    .(isset($opt['limit']) ? ' data-limit="'.(int) $opt['limit'].'"' : '').'></div>',
            };
        }, $html);
    }

    /**
     * The page's price grid: the plans in its group, priced in its default market
     * with its rating formula. Prices are ¢/kWh, from the current rates and TDSP fees.
     *
     * @return array{type: string, header: ?string, market: ?Market, formula: string, rows: array}|null
     */
    public static function priceGrid(Page $page): ?array
    {
        if (! $page->pricegrid_type || ! $page->pricegridGroup) {
            return null;
        }
        $catalog = app(Catalog::class);
        $market = $page->market ?? Market::where('status', 'Active')->orderBy('id')->first();
        $rates = $catalog->currentRates();
        $fee = $market ? $catalog->currentFees()[$market->id] ?? null : null;
        $formula = $page->rating_formula ?: 'avg_1000';
        $rows = [];
        foreach ($page->pricegridGroup->plans()->where('active', true)->get() as $plan) {
            $rate = $market ? $rates[$plan->id.'-'.$market->id] ?? null : null;
            if (! $rate || ! $fee) {
                continue;
            }
            $avg = fn (int $kwh) => Catalog::averagePrice($rate->energy, $fee->per_kwh, $fee->per_bill, $plan->mrc, $kwh);
            $rows[] = ['plan' => $plan, 'price' => $formula === 'energy' ? (float) $rate->energy : $avg((int) substr($formula, 4)),
                'avg' => [500 => $avg(500), 1000 => $avg(1000), 2000 => $avg(2000)]];
        }

        return ['type' => $page->pricegrid_type, 'header' => $page->pricegrid_header, 'market' => $market,
            'formula' => config('cms.rating_formulas.'.$formula), 'rows' => $rows];
    }

    /** @return array<string, string> zone => HTML of its components, in order */
    public static function zones(Page $page): array
    {
        $out = array_fill_keys(array_keys(config('cms.zones')), '');
        foreach ($page->components()->with('block')->get() as $c) {
            $b = $c->block;
            if ($b && ($b->site_id === null || $b->site_id === $page->site_id) && isset($out[$c->zone])) {
                $out[$c->zone] .= '<div class="cms-block" data-block="'.e($b->slug).'">'.self::expand($b->withVars($c->vars ?? []), $page->site).'</div>';
            }
        }

        return $out;
    }
}
