@extends('backend.layouts.app')

@section('crumb', $u->exists ? $u->name : 'New User')

@section('content')
    @include('backend.partials.page-head', ['title' => $u->exists ? $u->name : 'New User', 'sub' => $u->exists ? e($u->title ?? 'Employee').' · '.e($u->company ? $u->company.' (3rd party)' : ($u->department ?? 'No department')) : 'An employee, or a 3rd-party company user, who signs in to the admin.',
        'actions' => $u->exists ? '<a class="btn ghost" href="'.route('sheriff.users.show', $u).'">Profile</a><a class="btn ghost" href="'.route('sheriff.users.history', $u).'">History</a>'
            .(! $u->is(auth()->user()) ? '<button type="submit" form="toggle-user" class="btn '.($u->active ? 'red' : 'cyan').'">'.($u->active ? 'Disable' : 'Enable').'</button>' : '') : null])

    @php
        $role = $u->role;
        $changes = $u->accessChanges();
        $choice = fn ($perm) => old('access.'.$perm, ['added' => 'allow', 'removed' => 'deny'][$changes[$perm] ?? ''] ?? 'role');
        $roles_perms = $roles->mapWithKeys(fn ($r) => [$r->id => $r->perms ?? []]);
    @endphp

    <form method="post" action="{{ $u->exists ? route('sheriff.users.update', $u) : route('sheriff.users.store') }}" class="stack">@csrf @if ($u->exists) @method('patch') @endif
        <div class="grid-2 user-form-top">
        <div class="panel"><div class="panel-head"><h2>Person</h2></div>
            <div class="panel-body form-grid">
                <label for="name">Name</label><input id="name" name="name" required maxlength="150" value="{{ old('name', $u->name) }}">
                <label for="company">Company</label><div><input id="company" name="company" maxlength="120" value="{{ old('company', $u->company) }}" placeholder="Empty for Energy Texas employees"><div class="help">For 3rd-party users: the outside company they work for (call center, agency, vendor).</div></div>
                <label for="access_ends_on">Access ends</label><div><input id="access_ends_on" name="access_ends_on" type="date" value="{{ old('access_ends_on', $u->access_ends_on?->toDateString()) }}" style="max-width:20ch"><div class="help">Usually the end of the company's contract. Empty for no end date.</div></div>
                <label for="employee_id">Employee #</label><input id="employee_id" name="employee_id" maxlength="20" class="mono" value="{{ old('employee_id', $u->employee_id) }}" style="max-width:16ch">
                <label for="title">Job title</label><input id="title" name="title" maxlength="100" value="{{ old('title', $u->title) }}">
                <label>Department</label><div class="chips small"><label><input type="radio" name="department" value="" @checked(! old('department', $u->department))><span>None</span></label>@foreach (config('admin.departments') as $d)<label><input type="radio" name="department" value="{{ $d }}" @checked(old('department', $u->department) === $d)><span>{{ $d }}</span></label>@endforeach</div>
                <label>Manager</label><div class="table-wrap pick-list" style="max-height:200px"><table class="table"><tbody>
                    <tr><td style="width:32px"><input type="radio" id="mgr0" name="manager_id" value="" @checked(! old('manager_id', $u->manager_id))></td><td><label for="mgr0">None</label></td></tr>
                    @foreach ($managers as $m)<tr><td><input type="radio" id="mgr{{ $m->id }}" name="manager_id" value="{{ $m->id }}" @checked(old('manager_id', $u->manager_id) == $m->id)></td><td><label for="mgr{{ $m->id }}"><b>{{ $m->name }}</b></label>@if ($m->title) <span class="help">{{ $m->title }}</span>@endif</td></tr>@endforeach
                </tbody></table></div>
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
        </div>

        <div class="panel"><div class="panel-head"><h2>Access</h2></div>
            <div class="panel-body form-grid">
                <label>Role</label><div><div class="chips small" data-role-select>@foreach ($roles as $r)<label><input type="radio" name="role_id" value="{{ $r->id }}" @checked(old('role_id', $u->role_id ?? $roles->first()->id) == $r->id)><span>{{ $r->name }}</span></label>@endforeach</div>
                    <div class="help">The role sets what they get by default. Change single apps or rights for this person below.</div></div>
            </div>
            <div class="table-wrap"><table class="table access-table">
                <thead><tr><th>App or right</th><th>From their role</th><th>For this person</th></tr></thead>
                <tbody>
                    @foreach ($apps as $key => $a)
                        <tr data-perm="{{ $key }}"><td><b>{{ $a['name'] }}</b></td><td class="role-has"></td>
                            <td class="access-choices">@foreach (['role' => 'Role default', 'allow' => 'Allow', 'deny' => 'Deny'] as $v => $l)<label class="check" style="margin-right:12px"><input type="radio" name="access[{{ $key }}]" value="{{ $v }}" @checked($choice($key) === $v)> {{ $l }}</label>@endforeach</td></tr>
                    @endforeach
                    @foreach ($rights as $key => $label)
                        <tr data-perm="{{ $key }}"><td><b>{{ $label }}</b></td><td class="role-has"></td>
                            <td class="access-choices">@foreach (['role' => 'Role default', 'allow' => 'Allow', 'deny' => 'Deny'] as $v => $l)<label class="check" style="margin-right:12px"><input type="radio" name="access[{{ $key }}]" value="{{ $v }}" @checked($choice($key) === $v)> {{ $l }}</label>@endforeach</td></tr>
                    @endforeach
                </tbody>
            </table></div>
            {{-- Shown when this person has an Allow or Deny: a short reason, listed under their rights on the Users page --}}
            <div class="panel-body form-grid" id="access-reason">
                <label for="access_note">Reason for the exceptions</label><div><input id="access_note" name="access_note" maxlength="250" value="{{ old('access_note', $u->access_note) }}" placeholder="e.g. Deletes go through the CS Lead">
                    <div class="help">Why this person gets more or less than their role (the Allow and Deny choices above). It shows under their rights on the Users list.</div></div>
            </div>
        </div>

        <div class="actions"><button class="btn cyan">{{ $u->exists ? 'Save User' : 'Add User' }}</button><a class="btn ghost" href="{{ route('sheriff.users.index') }}">Cancel</a>
</div>
    </form>
    @if ($u->exists && ! $u->is(auth()->user()))<form id="toggle-user" method="post" action="{{ route('sheriff.users.update', $u) }}" data-confirm="{{ $u->active ? 'Disable '.$u->name.'?|They won\'t be able to sign in.|Disable' : 'Enable '.$u->name.'?|They can sign in again.|Enable' }}">@csrf @method('patch')<input type="hidden" name="active" value="{{ $u->active ? 0 : 1 }}"></form>@endif

    <script>
    (function () {
        var perms = @json($roles_perms), sel = document.querySelector('[data-role-select]');
        function current() { var c = sel.querySelector('input:checked'); return c ? c.value : ''; }
        function show() {
            var has = perms[current()] || [];
            document.querySelectorAll('.access-table tr[data-perm]').forEach(function (tr) {
                tr.querySelector('.role-has').innerHTML = has.indexOf(tr.dataset.perm) >= 0 ? '<span class="pill ok">Yes</span>' : '<span class="pill">No</span>';
            });
        }
        sel.addEventListener('change', show); show();
        // The reason only matters when something is allowed or denied for this person
        var reason = document.getElementById('access-reason');
        function exceptions() { reason.hidden = !document.querySelector('.access-table input[value=allow]:checked, .access-table input[value=deny]:checked'); }
        document.querySelectorAll('.access-table input[type=radio]').forEach(function (r) { r.addEventListener('change', exceptions); }); exceptions();
    })();
    </script>
@endsection
