@extends('backend.layouts.app')

@section('crumb', $u->exists ? $u->name : 'New User')

@section('content')
    @include('backend.partials.page-head', ['title' => $u->exists ? $u->name : 'New User', 'sub' => $u->exists ? e($u->title ?? 'Employee').' · '.e($u->department ?? 'No department') : 'An employee who signs in to the admin.',
        'actions' => $u->exists ? '<a class="btn ghost" href="'.route('sheriff.users.history', $u).'">History</a>' : null])

    @php
        $role = $u->role;
        $changes = $u->accessChanges();
        $choice = fn ($perm) => old('access.'.$perm, ['added' => 'allow', 'removed' => 'deny'][$changes[$perm] ?? ''] ?? 'role');
        $roles_perms = $roles->mapWithKeys(fn ($r) => [$r->id => $r->perms ?? []]);
    @endphp

    <form method="post" action="{{ $u->exists ? route('sheriff.users.update', $u) : route('sheriff.users.store') }}" class="stack" style="max-width:980px">@csrf @if ($u->exists) @method('patch') @endif
        <div class="panel"><div class="panel-head"><h2>Employee</h2></div>
            <div class="panel-body form-grid">
                <label for="name">Name</label><input id="name" name="name" required maxlength="150" value="{{ old('name', $u->name) }}">
                <label for="employee_id">Employee #</label><input id="employee_id" name="employee_id" maxlength="20" class="mono" value="{{ old('employee_id', $u->employee_id) }}" style="max-width:16ch">
                <label for="title">Job title</label><input id="title" name="title" maxlength="100" value="{{ old('title', $u->title) }}">
                <label for="department">Department</label><select id="department" name="department"><option value="">None</option>@foreach (config('admin.departments') as $d)<option @selected(old('department', $u->department) === $d)>{{ $d }}</option>@endforeach</select>
                <label for="manager_id">Manager</label><select id="manager_id" name="manager_id"><option value="">None</option>@foreach ($managers as $m)<option value="{{ $m->id }}" @selected(old('manager_id', $u->manager_id) == $m->id)>{{ $m->name }}{{ $m->title ? ' — '.$m->title : '' }}</option>@endforeach</select>
                <label for="location">Location</label><input id="location" name="location" maxlength="100" value="{{ old('location', $u->location) }}">
                <label for="phone_ext">Phone ext.</label><input id="phone_ext" name="phone_ext" maxlength="10" value="{{ old('phone_ext', $u->phone_ext) }}" style="max-width:10ch">
                <label for="hired_on">Hired</label><input id="hired_on" name="hired_on" type="date" value="{{ old('hired_on', $u->hired_on?->toDateString()) }}" style="max-width:20ch">
            </div>
        </div>

        <div class="panel"><div class="panel-head"><h2>Sign-in</h2></div>
            <div class="panel-body form-grid">
                <label for="email">Email</label><input id="email" name="email" type="email" required maxlength="200" value="{{ old('email', $u->email) }}">
                <label for="password">{{ $u->exists ? 'New password' : 'Temporary password' }}</label><div><input id="password" name="password" type="password" minlength="12" autocomplete="new-password" @required(! $u->exists)><div class="help">At least 12 characters.{{ $u->exists ? ' Leave empty to keep their password.' : '' }}</div></div>
            </div>
        </div>

        <div class="panel"><div class="panel-head"><h2>Access</h2></div>
            <div class="panel-body form-grid">
                <label for="role_id">Role</label><div><select id="role_id" name="role_id" data-role-select>@foreach ($roles as $r)<option value="{{ $r->id }}" @selected(old('role_id', $u->role_id) == $r->id)>{{ $r->name }}</option>@endforeach</select>
                    <div class="help">The role sets what they get by default. Change single apps or rights for this person below.</div></div>
            </div>
            <div class="table-wrap"><table class="table access-table">
                <thead><tr><th>App or right</th><th>From their role</th><th>For this person</th></tr></thead>
                <tbody>
                    @foreach ($apps as $key => $a)
                        <tr data-perm="{{ $key }}"><td><b>{{ $a['name'] }}</b><div class="help">{{ Str::limit($a['desc'], 90) }}</div></td><td class="role-has"></td>
                            <td class="nowrap">@foreach (['role' => 'Role default', 'allow' => 'Allow', 'deny' => 'Deny'] as $v => $l)<label class="check" style="margin-right:12px"><input type="radio" name="access[{{ $key }}]" value="{{ $v }}" @checked($choice($key) === $v)> {{ $l }}</label>@endforeach</td></tr>
                    @endforeach
                    @foreach ($rights as $key => $label)
                        <tr data-perm="{{ $key }}"><td><b>{{ $label }}</b><div class="help">Right</div></td><td class="role-has"></td>
                            <td class="nowrap">@foreach (['role' => 'Role default', 'allow' => 'Allow', 'deny' => 'Deny'] as $v => $l)<label class="check" style="margin-right:12px"><input type="radio" name="access[{{ $key }}]" value="{{ $v }}" @checked($choice($key) === $v)> {{ $l }}</label>@endforeach</td></tr>
                    @endforeach
                </tbody>
            </table></div>
            <div class="panel-body form-grid">
                <label for="access_note">Why it differs</label><input id="access_note" name="access_note" maxlength="250" value="{{ old('access_note', $u->access_note) }}" placeholder="e.g. Deletes go through the CS Lead">
            </div>
        </div>

        <div class="actions"><button class="btn cyan">{{ $u->exists ? 'Save User' : 'Add User' }}</button><a class="btn ghost" href="{{ route('sheriff.users.index') }}">Cancel</a>
            @if ($u->exists && ! $u->is(auth()->user()))<button type="submit" form="toggle-user" class="btn ghost">{{ $u->active ? 'Disable' : 'Enable' }}</button>@endif</div>
    </form>
    @if ($u->exists && ! $u->is(auth()->user()))<form id="toggle-user" method="post" action="{{ route('sheriff.users.update', $u) }}" data-confirm="{{ $u->active ? 'Disable '.$u->name.'?|They won\'t be able to sign in.|Disable' : 'Enable '.$u->name.'?|They can sign in again.|Enable' }}">@csrf @method('patch')<input type="hidden" name="active" value="{{ $u->active ? 0 : 1 }}"></form>@endif

    <script>
    (function () {
        var perms = @json($roles_perms), sel = document.querySelector('[data-role-select]');
        function show() {
            var has = perms[sel.value] || [];
            document.querySelectorAll('.access-table tr[data-perm]').forEach(function (tr) {
                tr.querySelector('.role-has').innerHTML = has.indexOf(tr.dataset.perm) >= 0 ? '<span class="pill ok">Yes</span>' : '<span class="pill">No</span>';
            });
        }
        sel.addEventListener('change', show); show();
    })();
    </script>
@endsection
