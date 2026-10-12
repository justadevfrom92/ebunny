<?php

namespace App\Http\Controllers\Backend\Lando;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Every plan the site can sell. Name, filters and bullets show on the website. */
class PlanController extends Controller
{
    public function index(): View
    {
        $plans = Plan::orderBy('type')->orderBy('term')->orderBy('name')->get();

        return view('backend.lando.plans.index', ['active' => $plans->where('active', true), 'inactive' => $plans->where('active', false)]);
    }

    public function create(): View
    {
        return view('backend.lando.plans.form', ['plan' => new Plan(['type' => 'Resi', 'term' => 12, 'rolloff' => 'JEY3', 'etf' => '$250', 'mrc' => 4.95, 'green' => 100, 'active' => true]), 'rolloffs' => $this->rolloffs()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Plan::create($this->validated($request));

        return redirect(app_route('plans.index'))->with('status', 'Plan created. Add its rates in Update Rates.');
    }

    public function edit(Plan $plan): View
    {
        return view('backend.lando.plans.form', ['plan' => $plan, 'rolloffs' => $this->rolloffs()]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->validated($request, $plan));

        return redirect(app_route('plans.index'))->with('status', 'Plan saved');
    }

    private function rolloffs()
    {
        return Plan::where('term', 1)->orderBy('internal')->pluck('internal');
    }

    private function validated(Request $request, ?Plan $plan = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'internal' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('plans')->ignore($plan)],
            'slug' => ['nullable', 'string', 'max:150'],
            'active' => ['required', 'boolean'],
            'type' => ['required', Rule::in(['Resi', 'Biz'])],
            'term' => ['required', 'integer', 'min:1', 'max:60'],
            'rolloff' => ['required', 'string', 'max:40'],
            'etf' => ['required', 'string', 'max:40'],
            'mrc' => ['required', 'numeric', 'min:0', 'max:999'],
            'green' => ['required', 'integer', 'min:0', 'max:100'],
            'tags' => ['nullable', 'string', 'max:200'],
            'perks' => ['nullable', 'string', 'max:2000'],
        ], ['internal.regex' => 'The internal name can only use letters and numbers.']);

        $lines = fn ($text, $sep) => collect(preg_split($sep, (string) $text))->map(fn ($t) => trim($t))->filter()->values()->all();
        $data['tags'] = $lines($data['tags'] ?? '', '/,/');
        $data['perks'] = $lines($data['perks'] ?? '', '/\R/');
        $data['slug'] = $data['slug'] ?: Str::slug(str_replace('&', 'and', $data['name']));

        return $data;
    }
}
