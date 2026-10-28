{{--
    The one layout every admin app uses. The sidebar, app switcher and menu come
    from config/admin.php, so each app only supplies its screens.
--}}
@php
    $appKey = \App\Support\AdminMenu::currentApp();
    \App\Support\AdminMenu::remember($appKey);
    $app = \App\Support\AdminMenu::appInfo($appKey);   // name and home page (the Account area too)
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
        {{-- Put the menu back where it was scrolled to before the page paints, so the link you clicked stays where it was (admin.js saves it) --}}
        <script>(function(m){try{var s=sessionStorage.getItem('admin-sidebar-scroll:'+(location.pathname.split('/')[2]||'home'));if(s!==null){m.scrollTop=+s}else{var on=m.querySelector('a.on');if(on)m.scrollTop=on.offsetTop-m.offsetTop-m.clientHeight/2}}catch(e){}})(document.getElementById('menu'))</script>
        <div class="side-foot">
            {{-- Always in the bottom-left corner, outside the scrolling menu --}}
            <a class="side-website" href="/" target="_blank" rel="noopener">Website</a>
        </div>
    </aside>
    <div class="main">
        <header class="topbar">
            <button class="menu-btn" id="menu-btn" aria-label="Open menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
            <div class="crumbs">{{-- every part is a link: the app's home, the menu item (or the page's own trail, e.g. the account you came from), this page --}}<a href="{{ $app['home'] }}">{{ $app['name'] }}</a> /@if (View::hasSection('crumb_trail')) @yield('crumb_trail') /@elseif ($activeItem && ! $activeItem['exact'] && View::hasSection('crumb') && trim(View::getSection('crumb')) !== $activeLabel) <a href="{{ $activeItem['url'] }}">{{ $activeLabel }}</a> /@endif <a href="{{ url()->full() }}" aria-current="page"><b>@yield('crumb', $activeLabel)</b></a></div>
            {{-- Shown when this browser has its own Style Guide changes (see partials/head) --}}
            <span class="local-style-flag" data-local-style-flag hidden><span data-local-style-text>Your style changes</span><button type="button" data-local-style-reset>Reset</button></span>
            @include('backend.partials.user-menu')
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
