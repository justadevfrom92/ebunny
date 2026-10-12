<?php

namespace App\Http\Controllers\Admin\Lando;

use App\Http\Controllers\Controller;
use App\Models\Market;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Utility service territories. A new market also needs zip ranges, rates and TDSP fees before the website can sell in it. */
class MarketController extends Controller
{
    public function index(): View
    {
        return view('admin.lando.markets.index', ['markets' => Market::withCount(['zipRanges', 'fees'])->orderBy('id')->get()]);
    }

    public function create(): View
    {
        return view('admin.lando.markets.form', ['market' => new Market(['type' => 'TDSP', 'state' => 'TX', 'commodity' => 'Electric', 'units' => 'kWh', 'status' => 'Active'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $market = Market::create($this->validated($request));

        return redirect()->route('sheriff.markets.edit', $market)->with('status', 'Market added. Add its TDSP fees and rates next.');
    }

    public function edit(Market $market): View
    {
        return view('admin.lando.markets.form', ['market' => $market->loadCount(['zipRanges', 'fees'])]);
    }

    public function update(Request $request, Market $market): RedirectResponse
    {
        $market->update($this->validated($request, $market));

        return redirect()->route('sheriff.markets.index')->with('status', 'Market saved');
    }

    private function validated(Request $request, ?Market $market = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:40', 'regex:/^[A-Z]{2}-[EG]-[A-Z0-9-]+$/', Rule::unique('markets')->ignore($market)],
            'short' => ['required', 'string', 'max:30'],
            'description' => ['required', 'string', 'max:120'],
            'region' => ['required', 'string', 'max:30'],
            'type' => ['required', Rule::in(['TDSP', 'Utility', 'Co-op', 'Municipal'])],
            'state' => ['required', 'string', 'size:2', 'alpha'],
            'commodity' => ['required', Rule::in(['Electric', 'Gas'])],
            'units' => ['required', Rule::in(['kWh', 'Therms', 'CCF'])],
            'phone' => ['nullable', 'string', 'max:20'],
            'duns_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]*$/'],
            'edi_name' => ['nullable', 'string', 'max:60'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ], ['name.regex' => 'Use the STATE-COMMODITY-UTILITY format, e.g. TX-E-ONCOR.']);
    }
}
