{{-- Header-only layout for pages outside any one app: the launcher and the admin app form. --}}
@php $user = auth()->user(); @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    @include('backend.partials.head')
    <title>@yield('title') · {{ config('brand.name') }} Admin</title>
</head>
<body>
@include('backend.partials.login-as-bar')
<div class="launcher">
    <header class="launcher-top"><div class="in">
        <a class="brand" href="{{ route('admin.launcher') }}" style="color:#fff;text-decoration:none"><img src="/shared/img/logo-mark-on-dark.svg" alt="" width="36" height="36"><div><strong>{{ config('brand.wordmark') }}</strong><span>Admin Tools</span></div></a>
        <div class="user-chip"><span class="avatar">{{ $user->initials() }}</span><span><span>{{ $user->name }}</span><small>{{ $user->role?->name }}</small></span></div>
        @include('backend.partials.test-switch')
        <form method="post" action="{{ route('admin.logout') }}" class="inline">@csrf<button class="btn sm ghost">Logout</button></form>
    </div></header>
    <main class="launcher-main">
        @include('backend.partials.flash')
        @yield('content')
    </main>
</div>
<div class="toast" id="toast" role="status" aria-live="polite" data-message="{{ session('status') }}"></div>
</body>
</html>
