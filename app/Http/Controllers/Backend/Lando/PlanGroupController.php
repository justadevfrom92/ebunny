<?php

namespace App\Http\Controllers\Backend\Lando;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanGroup;
use App\Support\History;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Groups decide which plans each website section shows, e.g. "featured" on the home page. */
class PlanGroupController extends Controller
{
    public function index(): View
    {
        return view('backend.lando.groups', [
            'groups' => PlanGroup::with('plans')->orderBy('id')->get(),
            'plans' => Plan::where('active', true)->orderBy('type')->orderBy('term')->get(),
        ]);
    }

    public function create(): View
    {
        return view('backend.lando.groups-form', ['group' => new PlanGroup, 'plans' => Plan::where('active', true)->orderBy('type')->orderBy('term')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $group = DB::transaction(function () use ($data) {
            $group = PlanGroup::create(['name' => $data['name'], 'slug' => $data['slug']]);
            $group->plans()->sync(collect($data['plans'] ?? [])->values()->mapWithKeys(fn ($id, $i) => [$id => ['position' => $i]])->all());

            return $group;
        });

        return redirect(app_route('groups.index'))->with('status', 'Group '.$group->name.' added');
    }

    public function edit(PlanGroup $group): View
    {
        return view('backend.lando.groups-form', ['group' => $group->load('plans'), 'plans' => Plan::where('active', true)->orderBy('type')->orderBy('term')->get()]);
    }

    /** Rename the group and set which plans it shows, in order. */
    public function update(Request $request, PlanGroup $group): RedirectResponse
    {
        $data = $this->validated($request, $group);
        DB::transaction(function () use ($group, $data) {
            $group->update(['name' => $data['name'], 'slug' => $data['slug']]);
            $group->plans()->sync(collect($data['plans'] ?? [])->values()->mapWithKeys(fn ($id, $i) => [$id => ['position' => $i]])->all());
        });
        History::record(['model' => 'PlanGroup_model', 'group' => 'Admin Changes', 'record_id' => $group->id, 'action' => 'updated',
            'summary' => $group->name.' plans set', 'data' => ['group' => $group->slug, 'plans' => $group->plans()->pluck('internal')->implode(', ')]]);

        return redirect(app_route('groups.index'))->with('status', $group->name.' saved');
    }

    private function validated(Request $request, ?PlanGroup $group = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9][a-z0-9-]*$/', Rule::unique('plan_groups')->ignore($group)],
            'plans' => ['nullable', 'array'],
            'plans.*' => ['integer', 'distinct', 'exists:plans,id'],
        ], ['slug.regex' => 'Use lowercase letters, numbers and dashes. The website looks groups up by this.']);
    }

    public function attach(Request $request, PlanGroup $group): RedirectResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'exists:plans,id']]);
        $group->plans()->syncWithoutDetaching([$data['plan_id'] => ['position' => $group->plans()->count()]]);
        $code = Plan::whereKey($data['plan_id'])->value('internal');
        History::record(['model' => 'PlanGroup_model', 'group' => 'Admin Changes', 'record_id' => $group->id, 'action' => 'updated',
            'summary' => $code.' added to '.$group->name, 'data' => ['group' => $group->slug, 'plans' => $group->plans()->pluck('internal')->implode(', ')]]);

        return back()->with('status', 'Plan added to '.$group->name);
    }

    public function detach(PlanGroup $group, Plan $plan): RedirectResponse
    {
        $group->plans()->detach($plan->id);
        History::record(['model' => 'PlanGroup_model', 'group' => 'Admin Changes', 'record_id' => $group->id, 'action' => 'updated',
            'summary' => $plan->internal.' removed from '.$group->name, 'data' => ['group' => $group->slug, 'plans' => $group->plans()->pluck('internal')->implode(', ')]]);

        return back()->with('status', $plan->internal.' removed from '.$group->name);
    }
}
