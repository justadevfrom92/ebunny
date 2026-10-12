{{--
  Website layout for pages Laravel builds (Lando pages, sign-up, My Account).
  Header, footer and styling come from the same files as the built-in pages.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@hasSection('full-title')@yield('full-title')@else@yield('title') | {{ $siteName ?? config('brand.name') }}@endif</title>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  @yield('head')
  <link rel="stylesheet" href="/css/style.css">
  <script src="/shared/boot.js" data-then="/js/main.js"></script>
</head>
<body @yield('body-attrs')>
  <div id="site-header"></div>
  @yield('notice')

  <main>
    <section class="page-banner">
      <div class="wrap">
        <div class="crumbs"><a href="/">Home</a> / @yield('crumb')</div>
        @hasSection('eyebrow')<p class="eyebrow">@yield('eyebrow')</p>@endif
        <h1>@yield('heading')</h1>
        @yield('banner')
      </div>
    </section>
    @yield('main')
  </main>

  <div id="site-footer"></div>
</body>
</html>
