<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\AdminApp;
use App\Models\Role;
use App\Support\AdminApps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin apps created from the launcher's "New Admin App" button. Each gets a tile,
 * its own sidebar of links, and a permission key that roles can grant.
 * Needs the "manage-apps" gate (roles with Sheriff).
 */
class AppController extends Controller
{
    /** A custom app's home page: its links shown as tiles. */
    public function show(Request $request, string $appKey): View|RedirectResponse
    {
        $app = AdminApps::get($appKey);
        abort_unless($app && $app['custom'], 404);
        if (! $request->user()->hasPerm($appKey)) {
            return redirect()->route('admin.launcher', ['denied' => $appKey]);
        }

        return view('backend.apps.show', ['app' => $app, 'appKey' => $appKey, 'canManage' => Gate::allows('manage-apps')]);
    }

    public function create(): View
    {
        Gate::authorize('manage-apps');

        return $this->form(new AdminApp(['icon' => 'folder', 'menu' => []]), Role::where('name', 'Administrator')->pluck('id')->all());
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-apps');
        $data = $this->validated($request);

        $app = DB::transaction(function () use ($data, $request) {
            $app = AdminApp::create([
                'key' => $data['key'], 'name' => $data['name'], 'description' => $data['description'], 'icon' => $data['icon'],
                'menu' => $data['menu'], 'position' => (int) AdminApp::max('position') + 1, 'created_by' => $request->user()->id,
            ]);
            $this->syncRoles($app->key, $data['roles']);

            return $app;
        });

        return redirect()->route('custom.show', $app->key)->with('status', $app->name.' created');
    }

    public function edit(AdminApp $app): View
    {
        Gate::authorize('manage-apps');
        $roles = Role::all()->filter(fn ($r) => in_array($app->key, $r->perms, true))->pluck('id')->all();

        return $this->form($app, $roles);
    }

    public function update(Request $request, AdminApp $app): RedirectResponse
    {
        Gate::authorize('manage-apps');
        $data = $this->validated($request, $app);

        DB::transaction(function () use ($app, $data) {
            $app->update(['name' => $data['name'], 'description' => $data['description'], 'icon' => $data['icon'], 'menu' => $data['menu']]);
            $this->syncRoles($app->key, $data['roles']);
        });

        return redirect()->route('custom.show', $app->key)->with('status', $app->name.' saved');
    }

    public function destroy(AdminApp $app): RedirectResponse
    {
        Gate::authorize('manage-apps');
        DB::transaction(function () use ($app) {
            $this->syncRoles($app->key, []);
            $app->delete();
        });

        return redirect()->route('admin.launcher')->with('status', $app->name.' deleted');
    }

    private function form(AdminApp $app, array $roleIds): View
    {
        return view('backend.apps.form', [
            'app' => $app,
            'icons' => AdminApps::icons(),
            'screens' => AdminApps::screens(),
            'roles' => Role::orderBy('id')->get(),
            'roleIds' => old('roles', $roleIds),
            'rows' => old('menu', $app->menu ?: [['heading' => '', 'label' => '', 'route' => '', 'url' => '']]),
        ]);
    }

    private function validated(Request $request, ?AdminApp $app = null): array
    {
        $request->merge(['key' => $app?->key ?? Str::slug($request->input('key') ?: $request->input('name'))]);
        $taken = array_merge(array_keys(config('admin.apps')), AdminApps::RESERVED);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'key' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9][a-z0-9-]*$/', Rule::notIn($taken), Rule::unique('admin_apps', 'key')->ignore($app)],
            'description' => ['required', 'string', 'max:200'],
            'icon' => ['required', Rule::in(array_keys(AdminApps::icons()))],
            'roles' => ['array'],
            'roles.*' => ['exists:roles,id'],
            'menu' => ['array'],
            'menu.*.heading' => ['nullable', 'string', 'max:40'],
            'menu.*.label' => ['nullable', 'string', 'max:60'],
            'menu.*.route' => ['nullable', Rule::in(array_keys(AdminApps::screens()))],
            'menu.*.url' => ['nullable', 'string', 'max:500', 'regex:#^(https?://|/)#'],
        ], [
            'key.not_in' => 'That address is already used by another admin app. Pick a different name or address.',
            'key.unique' => 'An admin app with that address already exists.',
            'menu.*.url.regex' => 'Links must start with https://, http:// or /.',
        ]);

        // Keep complete rows only: a label plus a screen or a URL
        $data['menu'] = collect($data['menu'] ?? [])
            ->filter(fn ($r) => filled($r['label'] ?? null) && (filled($r['route'] ?? null) || filled($r['url'] ?? null)))
            ->map(fn ($r) => ['heading' => trim($r['heading'] ?? '') ?: 'Links', 'label' => trim($r['label']),
                'route' => $r['route'] ?? null ?: null, 'url' => ($r['route'] ?? null) ? null : $r['url']])
            ->values()->all();

        // Administrator always gets new apps
        $data['roles'] = array_unique(array_merge($data['roles'] ?? [], Role::where('name', 'Administrator')->pluck('id')->all()));

        return $data;
    }

    /** Give exactly these roles the app's permission key. */
    private function syncRoles(string $key, array $roleIds): void
    {
        foreach (Role::all() as $role) {
            $perms = array_values(array_diff($role->perms, [$key]));
            if (in_array($role->id, array_map('intval', $roleIds), true)) {
                $perms[] = $key;
            }
            if ($perms !== $role->perms) {
                $role->update(['perms' => $perms]);
            }
        }
    }
}
