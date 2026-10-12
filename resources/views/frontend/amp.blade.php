{{-- The page's AMP Content, as an AMP page (Lando → Edit Page → AMP Content). --}}
<!doctype html>
<html ⚡ lang="en">
<head>
  <meta charset="utf-8">
  <script async src="https://cdn.ampproject.org/v0.js"></script>
  <title>{{ $page->html_title ?: $page->title.' | '.$site->name }}</title>
  <link rel="canonical" href="{{ $page->liveUrl() }}">
  <meta name="viewport" content="width=device-width">
  @if ($page->meta_description)<meta name="description" content="{{ $page->meta_description }}">@endif
  <style amp-boilerplate>body{-webkit-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-moz-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-ms-animation:-amp-start 8s steps(1,end) 0s 1 normal both;animation:-amp-start 8s steps(1,end) 0s 1 normal both}@-webkit-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-moz-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-ms-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-o-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}</style><noscript><style amp-boilerplate>body{-webkit-animation:none;-moz-animation:none;-ms-animation:none;animation:none}</style></noscript>
  <style amp-custom>body{font-family:Arial,sans-serif;color:#102247;margin:0}header{background:#102247;color:#fff;padding:14px 16px;font-weight:700}main{padding:16px}a{color:#0083c1}</style>
</head>
<body>
  <header><a href="{{ $page->liveUrl() }}" style="color:#fff;text-decoration:none">{{ $site->name }}</a></header>
  <main>
    <h1>{{ $page->contentSource()->page_title ?: $page->title }}</h1>
    {!! $content !!}
  </main>
</body>
</html>
