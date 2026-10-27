<?php

namespace App\Services;

use App\Models\ByopProduct;
use App\Models\Market;
use App\Models\Plan;
use App\Models\PlanGroup;
use App\Models\Rate;
use App\Models\TdspFee;
use App\Models\TermDiscount;
use App\Models\TermEtf;
use App\Models\ZipRange;
use Illuminate\Support\Collection;

/**
 * Current prices and the catalog feed the website reads.
 * Rates and TDSP fees keep history; "current" means the latest row effective today or earlier.
 */
class Catalog
{
    /** Current rate per plan and market, keyed "planId-marketId". */
    public function currentRates(): Collection
    {
        return Rate::whereDate('effective_on', '<=', today())
            ->orderBy('effective_on')->orderBy('id')
            ->get()
            ->keyBy(fn (Rate $r) => $r->plan_id.'-'.$r->market_id);
    }

    /** The newest scheduled rate per plan and market (may be in the future), keyed "planId-marketId". */
    public function latestRates(): Collection
    {
        return Rate::orderBy('effective_on')->orderBy('id')->get()
            ->keyBy(fn (Rate $r) => $r->plan_id.'-'.$r->market_id);
    }

    /** Current TDSP fee per market, keyed by market id. */
    public function currentFees(): Collection
    {
        return TdspFee::whereDate('effective_on', '<=', today())
            ->orderBy('effective_on')->orderBy('id')
            ->get()->keyBy('market_id');
    }

    /** Average price in ¢/kWh at a usage level, the way the Electricity Facts Label shows it. */
    public static function averagePrice(float $energy, float $tdspPerKwh, float $tdspPerBill, float $mrc, int $kwh): float
    {
        return $energy + $tdspPerKwh + (($tdspPerBill + $mrc) / $kwh) * 100;
    }

    /**
     * Everything the website's shared/js/core.js expects in ET.defaults.
     * Shapes match the website's original JavaScript catalog so the site code is unchanged.
     */
    public function siteData(): array
    {
        $markets = Market::orderBy('id')->get();
        $plans = Plan::orderBy('id')->get();
        $planCode = $plans->pluck('internal', 'id');
        $marketName = $markets->pluck('name', 'id');
        $regions = array_merge(['Generic'], $markets->pluck('region')->unique()->values()->all());

        $termRows = TermDiscount::all()->groupBy('term');
        $etfs = TermEtf::pluck('amount', 'term');

        return [
            'MARKETS' => $markets->map(fn ($m) => [
                'id' => $m->id, 'name' => $m->name, 'short' => $m->short, 'desc' => $m->description, 'region' => $m->region, 'phone' => $m->phone,
            ])->values(),
            'ZIP_RANGES' => ZipRange::with('market')->get()->map(fn ($z) => [$z->zip_from, $z->zip_to, $z->market->name])->values(),
            'TDSP_FEES' => $this->currentFees()->map(fn ($f) => [
                'market' => $marketName[$f->market_id], 'perKwh' => $f->per_kwh, 'perBill' => $f->per_bill,
                'kwhDate' => $f->effective_on->toDateString(), 'billDate' => $f->effective_on->toDateString(),
            ])->values(),
            'PLANS' => $plans->map(fn ($p) => [
                'id' => $p->id, 'type' => $p->type, 'term' => $p->term, 'name' => $p->name, 'internal' => $p->internal,
                'rolloff' => $p->rolloff, 'etf' => $p->etf, 'mrc' => $p->mrc, 'green' => $p->green, 'active' => $p->active,
                'tags' => $p->tags ?? [], 'perks' => $p->perks ?? [], 'slug' => $p->slug, 'page' => $p->pageSlug(),
            ])->values(),
            'PLAN_GROUPS' => PlanGroup::with('plans')->get()->map(fn ($g) => [
                'id' => $g->id, 'name' => $g->name, 'slug' => $g->slug, 'plans' => $g->plans->pluck('internal')->values(),
            ])->values(),
            'RATES' => $this->currentRates()->map(fn ($r) => [
                'plan' => $planCode[$r->plan_id], 'market' => $marketName[$r->market_id], 'energy' => $r->energy, 'effective' => $r->effective_on->toDateString(),
            ])->values(),
            'REGIONS' => $regions,
            'TERM_MODS' => $termRows->sortKeys()->map(function ($rows, $term) use ($regions, $etfs) {
                $row = ['term' => (int) $term];
                foreach ($regions as $region) {
                    $row[$region] = (float) optional($rows->firstWhere('region', $region))->discount;
                }
                $row['etf'] = (int) ($etfs[$term] ?? 0);

                return $row;
            })->values(),
            'BYOP_PRODUCTS' => ByopProduct::orderBy('position')->get()->map(fn ($p) => [
                'id' => $p->id, 'key' => $p->key, 'name' => $p->name, 'model' => $p->model, 'type' => $p->type,
                'adj' => $p->rate_adj, 'monthly' => $p->monthly, 'step' => $p->step, 'active' => $p->active, 'description' => $p->description,
            ])->values(),
        ];
    }
}
