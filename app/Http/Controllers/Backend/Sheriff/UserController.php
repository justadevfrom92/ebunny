<?php

namespace App\Http\Controllers\Backend\Sheriff;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminApps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Sheriff → Users: the employees who sign in to the admin. Each has a role, and access can be
 * added or taken away for one person on top of it (e.g. a CSR who may not make permanent deletes).
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::with(['role', 'manager'])->orderByDesc('active')->orderBy('name')->get();

        return view('backend.sheriff.users', [
            'groups' => $users->where('active', true)->groupBy(fn ($u) => $u->department ?? 'No department')->sortKeys(),
            'disabled' => $users->where('active', false),
            'apps' => AdminApps::all(),
            'rights' => config('admin.permissions'),
        ]);
    }

    public function create(): View
    {
        return $this->form(new User(['active' => true]));
    }

    public function edit(User $user): View
    {
        return $this->form($user);
    }

    private function form(User $user): View
    {
        return view('backend.sheriff.user-form', [
            'u' => $user,
            'roles' => Role::orderBy('name')->get(),
            'managers' => User::where('active', true)->whereKeyNot($user->id)->orderBy('name')->get(['id', 'name', 'title']),
            'apps' => AdminApps::all(),
            'rights' => config('admin.permissions'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $user = User::create($data + ['active' => true]);

        return redirect()->route('sheriff.users.index')->with('status', $user->name.' added. Share the temporary password with them securely.');
    }

    /** The edit form, or a quick change from the list (enable/disable). */
    public function update(Request $request, User $user): RedirectResponse
    {
        if (! $request->has('name')) {
            // Quick changes without the full form: enable/disable, or a new password
            if ($request->has('active')) {
                if ($user->is($request->user())) {
                    return back()->withErrors(['user' => 'You cannot disable your own account.']);
                }
                $user->update(['active' => $request->boolean('active')]);

                return back()->with('status', $user->name.($user->active ? ' enabled' : ' disabled'));
            }
            $user->update($request->validate(['password' => ['required', Password::min(12)]]));

            return back()->with('status', $user->name.'\'s password changed');
        }
        $data = $this->validated($request, $user);
        if ($user->is($request->user()) && ($data['role_id'] != $user->role_id || in_array('sheriff', $data['denied_perms'], true))) {
            return back()->withInput()->withErrors(['role_id' => 'You cannot change your own role or remove your own Sheriff access.']);
        }
        $user->update($data);

        return redirect()->route('sheriff.users.index')->with('status', $user->name.' saved');
    }

    private function validated(Request $request, ?User $user): array
    {
        $perms = array_merge(array_keys(AdminApps::all()), array_keys(config('admin.permissions')));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:200', Rule::unique('users', 'email')->ignore($user)],
            'employee_id' => ['nullable', 'string', 'max:20', Rule::unique('users', 'employee_id')->ignore($user)],
            'title' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', Rule::in(config('admin.departments'))],
            'manager_id' => ['nullable', 'exists:users,id', Rule::notIn([$user?->id])],
            'phone_ext' => ['nullable', 'string', 'max:10'],
            'location' => ['nullable', 'string', 'max:100'],
            'hired_on' => ['nullable', 'date'],
            'role_id' => ['required', 'exists:roles,id'],
            'access' => ['array'],
            'access.*' => [Rule::in(['role', 'allow', 'deny'])],
            'access_note' => ['nullable', 'string', 'max:250'],
            'password' => [$user ? 'nullable' : 'required', Password::min(12)],
        ]);
        // Per-person access: "allow" adds what the role lacks, "deny" takes away what it has
        $role = Role::find($data['role_id']);
        $data['extra_perms'] = $data['denied_perms'] = [];
        foreach ($data['access'] ?? [] as $perm => $choice) {
            if (! in_array($perm, $perms, true)) {
                continue;
            }
            $inRole = in_array($perm, $role->perms ?? [], true);
            if ($choice === 'allow' && ! $inRole) {
                $data['extra_perms'][] = $perm;
            } elseif ($choice === 'deny' && $inRole) {
                $data['denied_perms'][] = $perm;
            }
        }
        unset($data['access']);
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }
}
