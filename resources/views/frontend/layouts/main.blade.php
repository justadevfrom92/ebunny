{{--
  Website layout for pages Laravel builds (Lando pages, sign-up, My Account).
  Header, footer and styling come from the same files as the built-in pages.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  @hasSection('full-title')
  <title>@yield('full-title')</title>
  @else
  <title>@yield('title') | {{ $siteName ?? config('brand.name') }}</title>
  @endif
  <meta name="csrf-token" content="{{ csrf_token() }}">
  @yield('head')
  <link rel="stylesheet" href="/frontend/css/style.css">
  <script src="/shared/boot.js" data-then="/frontend/js/main.js"></script>
  <script src="/shared/password-toggle.js" defer></script>
  <script src="/shared/datepicker.js" defer></script>
</head>
<body @yield('body-attrs')>
  <div id="site-header"></div>
  @yield('notice')

  <main>
    {{-- No page banners. Pages whose title is content people need (a plan or product name, sign-up steps,
         a survey) ask for a plain heading on the page with @section('show-title', '1'); the rest keep the
         heading for screen readers only. --}}
    @hasSection('show-title')
    <div class="page-title"><div class="wrap">
        @hasSection('eyebrow')<p class="eyebrow">@yield('eyebrow')</p>@endif
        <h1>@yield('heading')</h1>
        @yield('banner')
    </div></div>
    @else
    <h1 class="sr-only">@yield('heading')</h1>
    @endif
    @yield('main')
  </main>

  <div id="site-footer"></div>
</body>
</html>
