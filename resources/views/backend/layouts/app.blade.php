{{--
    The one layout every admin app uses. The sidebar, app switcher and menu come
    from config/admin.php, so each app only supplies its screens.
--}}
@php
    $appKey = \App\Support\AdminMenu::currentApp();
    $app = \App\Support\AdminApps::get($appKey);
    $sections = \App\Support\AdminMenu::sections($appKey);
    $activeItem = collect($sections)->flatten(1)->firstWhere('active', true);
    $activeLabel = $activeItem['label'] ?? '';
    $user = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    @include('backend.partials.head')
    <title>@yield('title', $activeLabel) · {{ $app['name'] }} · {{ config('brand.name') }} Admin</title>
</head>
<body>
@include('backend.partials.login-as-bar')
<div class="shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <img src="/shared/img/logo-mark-on-dark.svg" alt="" width="36" height="36">
            <div><strong>{{ config('brand.wordmark') }}</strong><span>Admin Tools</span></div>
        </div>
        <span class="env">{{ strtoupper(app()->environment()) }}</span>
        {{-- One Admin button back to the launcher (the same page the website's Admin button opens) --}}
        <a class="admin-home" href="{{ route('admin.launcher') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
            <span>Admin<small>{{ $app['name'] }}</small></span>
        </a>
        <nav class="menu" id="menu" aria-label="{{ $app['name'] }} menu">
            @foreach ($sections as $heading => $items)
                @if (is_string($heading))<h4>{{ $heading }}</h4>@elseif (! $loop->first)<div class="menu-gap"></div>@endif
                @foreach ($items as $it)
                    <a href="{{ $it['url'] }}" @class(['on' => $it['active']]) @if ($it['active']) aria-current="page" @endif>{{ $it['label'] }}@if ($it['badge'])<span class="count hot">{{ $it['badge'] }}</span>@endif</a>
                @endforeach
            @endforeach
        </nav>
        <div class="side-foot">
            <a href="/" target="_blank" rel="noopener">Website</a>
        </div>
    </aside>
    <div class="main">
        <header class="topbar">
            <button class="menu-btn" id="menu-btn" aria-label="Open menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
            <div class="crumbs">{{ $app['name'] }} /@if ($activeItem && ! $activeItem['exact'] && View::hasSection('crumb') && trim(View::getSection('crumb')) !== $activeLabel) <a href="{{ $activeItem['url'] }}">{{ $activeLabel }}</a> /@endif <b>@yield('crumb', $activeLabel)</b></div>
            <div class="user-chip"><span class="avatar">{{ $user->initials() }}</span><span><span>{{ $user->name }}</span><small>{{ $user->role?->name }}</small></span></div>
            @include('backend.partials.test-switch')
            <form method="post" action="{{ route('admin.logout') }}" class="inline">@csrf<button class="btn sm ghost">Logout</button></form>
        </header>
        <main class="content">
            @include('backend.partials.flash')
            @yield('content')
        </main>
    </div>
</div>
<div class="toast" id="toast" role="status" aria-live="polite" data-message="{{ session('status') }}"></div>
@include('components.chart-tip')
</body>
</html>
