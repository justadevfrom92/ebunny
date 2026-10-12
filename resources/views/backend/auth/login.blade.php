<!DOCTYPE html>
<html lang="en">
<head>
    @include('backend.partials.head')
    <title>Admin Sign In · {{ config('brand.name') }}</title>
</head>
<body>
<div class="login-wrap">
    <form class="login" method="post" action="{{ route('admin.login.attempt') }}">
        @csrf
        <div class="brand">
            <img src="/shared/img/logo-mark-on-dark.svg" alt="" width="36" height="36">
            <div><strong>{{ config('brand.wordmark') }}</strong><span>Admin Tools</span></div>
        </div>
        @if (session('status'))<div class="flash ok">{{ session('status') }}</div>@endif
        <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus></div>
        <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
        <label class="check"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
        <div class="err" role="alert">{{ $errors->first() }}</div>
        <button class="btn cyan">Sign In</button>
        <a href="/" class="muted" style="text-align:center;font-size:.85rem">← Back to website</a>
    </form>
    @if ($testUsers->isNotEmpty())
        <div class="login test-logins">
            <h2>Test Sign-Ins</h2>
            <p class="muted">For testing only (never shown in production): sign in as any sample admin to see what their role can do.</p>
            @foreach ($testUsers as $u)
                <form method="post" action="{{ route('admin.login.test', $u) }}">@csrf
                    <button class="test-user"><span class="avatar">{{ $u->initials() }}</span><span><b>{{ $u->name }}</b><small>{{ $u->role?->name ?? 'No role' }} · {{ collect($u->role?->perms ?? [])->map(fn ($p) => \App\Support\AdminApps::get($p)['name'] ?? null)->filter()->implode(', ') ?: 'no apps' }}</small></span></button>
                </form>
            @endforeach
        </div>
    @endif
</div>
</body>
</html>
