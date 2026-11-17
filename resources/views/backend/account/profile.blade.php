@extends('backend.layouts.app')

@section('crumb', 'My Profile')

{{-- Your own profile: you can change your name and phone extension; the rest is set in Sheriff → Users --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'My Profile', 'sub' => e($u->title ?? 'Employee').' · '.e($u->company ? $u->company.' (3rd party)' : ($u->department ?? 'No department'))])

    <div class="grid-2">
        <form method="post" action="{{ route('admin.account.profile.save') }}" class="panel">@csrf @method('put')
            <div class="panel-head"><h2>Your Details</h2><span class="help">you can change these</span></div>
            <div class="panel-body">
                <div class="field"><label for="name">Name</label><input id="name" name="name" value="{{ old('name', $u->name) }}" required maxlength="120" autocomplete="name">
                    @error('name')<div class="help" style="color:var(--red)">{{ $message }}</div>@enderror</div>
                <div class="field"><label for="phone_ext">Phone Ext.</label><input id="phone_ext" name="phone_ext" value="{{ old('phone_ext', $u->phone_ext) }}" maxlength="20" inputmode="numeric">
                    @error('phone_ext')<div class="help" style="color:var(--red)">{{ $message }}</div>@enderror</div>
                <button class="btn cyan" style="margin-top:8px">Save Profile</button>
            </div>
        </form>

        <div class="panel"><div class="panel-head"><h2>Work Details</h2><span class="help">set by an administrator in Sheriff</span></div><div class="panel-body"><dl class="kv">
            <dt>Email</dt><dd>{{ $u->email }}</dd>
            <dt>Role</dt><dd>{{ $u->role?->name ?? '—' }}</dd>
            <dt>Title</dt><dd>{{ $u->title ?? '—' }}</dd>
            @if ($u->company)
                <dt>Company</dt><dd>{{ $u->company }} <span class="pill info">3rd party</span></dd>
            @else
                <dt>Department</dt><dd>{{ $u->department ?? '—' }}</dd>
                <dt>Employee #</dt><dd class="mono">{{ $u->employee_id ?? '—' }}</dd>
            @endif
            <dt>{{ $u->company ? 'Our Contact' : 'Manager' }}</dt><dd>{{ $u->manager?->name ?? '—' }}</dd>
            <dt>Started</dt><dd>{{ $u->hired_on?->format('n/j/Y') ?? '—' }}@if ($u->tenure()) <span class="muted">({{ $u->tenure() }})</span>@endif</dd>
            <dt>Location</dt><dd>{{ $u->location ?? '—' }}</dd>
        </dl></div></div>
    </div>
@endsection
