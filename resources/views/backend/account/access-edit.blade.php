@extends('backend.layouts.app')

@section('crumb', $name)

{{-- Administrators: your own access to one app or right, from What You Can Open --}}
@section('content')
    @include('backend.partials.page-head', ['title' => $name, 'sub' => ($kind === 'app' ? 'Admin app' : 'Right').' · your access: '.($has ? ($kind === 'app' ? 'can open' : 'allowed') : 'none'),
        'actions' => '<a class="btn ghost" href="'.route('admin.account.profile').'#access">Back to My Profile</a>'])
    @error('access')<div class="alert bad" role="alert">{{ $message }}</div>@enderror

    <form method="post" action="{{ route('admin.account.access.save', $perm) }}" class="panel">@csrf @method('put')
        <div class="panel-head"><h2>Your Access</h2><span class="help">your role, {{ $u->role?->name ?? 'none' }}, {{ $inRole ? 'includes' : 'does not include' }} it</span></div>
        <div class="panel-body">
            @if ($desc)<p class="help">{{ $desc }}</p>@endif
            <div class="field"><label>Access</label><div class="chips small" aria-label="Access">
                <label><input type="radio" name="access" value="role" @checked($choice === 'role')><span>From my role ({{ $inRole ? 'yes' : 'no' }})</span></label>
                <label><input type="radio" name="access" value="allow" @checked($choice === 'allow')><span>Always allow</span></label>
                <label><input type="radio" name="access" value="deny" @checked($choice === 'deny') @disabled($perm === 'sheriff')><span>Never allow</span></label>
            </div></div>
            @if ($perm === 'sheriff')<p class="help">You can't take away your own Sheriff access.</p>@endif
            <div class="field" style="margin-top:12px"><label for="access_note">Reason (shown in Sheriff)</label><input id="access_note" name="access_note" maxlength="250" value="{{ old('access_note', $u->access_note) }}"></div>
            <button class="btn cyan" style="margin-top:8px">Save Access</button>
        </div>
    </form>
@endsection
