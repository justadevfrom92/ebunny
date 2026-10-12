<?php

namespace App\Http\Controllers\Backend\Sheriff;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\AdminApps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Which apps and rights each role has. Administrator always has everything. */
class RoleController extends Controller
{
    public static function perms(): array
    {
        return collect(AdminApps::all())->map(fn ($a) => $a['custom'] ? $a['name'] : $a['name'].' ('.explode(':', $a['desc'])[0].')')
            ->merge(config('admin.permissions'))->all();
    }

    public function index(): View
    {
        return view('backend.sheriff.roles', ['roles' => Role::withCount('users')->orderBy('id')->get(), 'perms' => self::perms()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $valid = array_keys(self::perms());
        $data = $request->validate(['perms' => ['array'], 'perms.*' => ['array'], 'perms.*.*' => ['in:'.implode(',', $valid)]]);

        foreach (Role::all() as $role) {
            if ($role->isAdministrator()) {
                continue;
            }
            $role->update(['perms' => array_values(array_intersect($valid, $data['perms'][$role->id] ?? []))]);
        }

        return back()->with('status', 'Roles saved');
    }
}
