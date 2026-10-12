<?php

namespace App\Http\Controllers\Backend\Astro;

use App\Http\Controllers\Controller;
use App\Models\ByopProduct;
use App\Models\Market;
use App\Models\TermDiscount;
use App\Models\TermEtf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Term discounts, ETFs and Build Your Own Plan products — all read by the website's plan builder. */
class PricingController extends Controller
{
    private function regions(): array
    {
        return array_merge(['Generic'], Market::orderBy('id')->pluck('region')->unique()->values()->all());
    }

    public function terms(): View
    {
        return view('backend.astro.terms', [
            'regions' => $this->regions(),
            'grid' => TermDiscount::all()->groupBy('term')->map(fn ($rows) => $rows->pluck('discount', 'region'))->sortKeys(),
        ]);
    }

    public function updateTerms(Request $request): RedirectResponse
    {
        $data = $request->validate(['d' => ['required', 'array'], 'd.*' => ['array'], 'd.*.*' => ['required', 'numeric', 'min:-20', 'max:20']]);
        $regions = $this->regions();
        DB::transaction(function () use ($data, $regions) {
            foreach ($data['d'] as $term => $byRegion) {
                foreach ($byRegion as $region => $value) {
                    if ((int) $term >= 1 && (int) $term <= 36 && in_array($region, $regions, true)) {
                        TermDiscount::updateOrCreate(['term' => (int) $term, 'region' => $region], ['discount' => $value]);
                    }
                }
            }
        });

        return back()->with('status', 'Term modifiers saved');
    }

    public function etfs(): View
    {
        return view('backend.astro.etfs', ['etfs' => TermEtf::orderBy('term')->get()]);
    }

    public function updateEtfs(Request $request): RedirectResponse
    {
        $data = $request->validate(['etf' => ['required', 'array'], 'etf.*' => ['required', 'integer', 'min:0', 'max:5000']]);
        foreach ($data['etf'] as $term => $amount) {
            if ((int) $term >= 1 && (int) $term <= 36) {
                TermEtf::updateOrCreate(['term' => (int) $term], ['amount' => $amount]);
            }
        }

        return back()->with('status', 'ETFs saved');
    }

    public function products(): View
    {
        return view('backend.astro.products', ['products' => ByopProduct::orderBy('position')->get()]);
    }

    public function updateProducts(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'p' => ['required', 'array'],
            'p.*.rate_adj' => ['required', 'numeric', 'min:-10', 'max:10'],
            'p.*.monthly' => ['required', 'numeric', 'min:0', 'max:500'],
            'p.*.active' => ['required', 'boolean'],
        ]);
        foreach ($data['p'] as $id => $values) {
            ByopProduct::whereKey($id)->first()?->update($values);
        }

        return back()->with('status', 'Products saved. The website uses these prices now.');
    }

    /** Modifiers → Products: how far each add-on can be discounted (¢/kWh). */
    public function modifierProducts(): View
    {
        return view('backend.astro.modifier-products', ['products' => ByopProduct::orderBy('position')->get()]);
    }

    public function updateModifierProducts(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'p' => ['required', 'array'],
            'p.*.min_discount' => ['nullable', 'numeric', 'min:-10', 'max:10'],
            'p.*.max_discount' => ['nullable', 'numeric', 'min:-10', 'max:10'],
        ]);
        foreach ($data['p'] as $values) {
            if (isset($values['min_discount'], $values['max_discount']) && $values['min_discount'] < $values['max_discount']) {
                // Discounts are negative: the "max" discount is the larger cut, so it must be <= the min
                return back()->withInput()->withErrors(['p' => 'Max Discount must be the same as or a bigger discount (more negative) than Min Discount.']);
            }
        }
        foreach ($data['p'] as $id => $values) {
            ByopProduct::whereKey($id)->first()?->update(['min_discount' => $values['min_discount'] ?? null, 'max_discount' => $values['max_discount'] ?? null]);
        }

        return back()->with('status', 'Discount limits saved');
    }

    /** Upload → BYOP Discounts: a CSV of key,rate_adj[,min_discount,max_discount] applied to the BYOP products. */
    public function byopUpload(): View
    {
        return view('backend.astro.byop-upload', ['products' => ByopProduct::orderBy('position')->get(['key', 'name', 'rate_adj', 'min_discount', 'max_discount'])]);
    }

    public function byopImport(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:512', 'mimes:csv,txt']]);
        $rows = array_map('str_getcsv', preg_split('/\r\n|\r|\n/', trim($request->file('file')->get())));
        if ($rows && ! is_numeric($rows[0][1] ?? null)) {
            array_shift($rows); // header row
        }

        $products = ByopProduct::pluck('id', 'key');
        $errors = [];
        $updates = [];
        foreach ($rows as $i => $r) {
            $line = $i + 2;
            $key = trim($r[0] ?? '');
            $nums = array_map(fn ($v) => trim((string) $v) === '' ? null : trim($v), array_slice($r, 1, 3)) + [null, null, null];
            if ($key === '') {
                continue;
            }
            if (! isset($products[$key])) {
                $errors[] = "Line $line: no product with key \"$key\"";

                continue;
            }
            foreach ($nums as $n) {
                if ($n !== null && (! is_numeric($n) || abs((float) $n) > 10)) {
                    $errors[] = "Line $line: \"$n\" is not a number between -10 and 10";

                    continue 2;
                }
            }
            $updates[$products[$key]] = array_filter(['rate_adj' => $nums[0], 'min_discount' => $nums[1], 'max_discount' => $nums[2]], fn ($v) => $v !== null);
        }
        if ($errors) {
            return back()->withErrors(['file' => array_slice($errors, 0, 10)]);
        }
        if (! $updates) {
            return back()->withErrors(['file' => 'The file has no product rows.']);
        }

        DB::transaction(function () use ($updates) {
            foreach ($updates as $id => $values) {
                ByopProduct::find($id)->update($values);
            }
        });

        return redirect()->route('astro.modifiers.products')->with('status', count($updates).' BYOP products updated from the upload');
    }
}
