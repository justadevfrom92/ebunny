{{-- Shown instead of a website page to a blocked IP or in a blocked area (Lando → Site). No site script, so it isn't counted twice. --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Not available | {{ config('brand.name') }}</title>
  <link rel="stylesheet" href="/css/style.css">
</head>
<body>
  <main class="section"><div class="wrap" style="max-width:640px;text-align:center;padding:80px 0">
    <h1>Not available</h1>
    <p>{{ $block->message ?: 'This page isn\'t available.' }}</p>
    <p><a href="/">Go to the home page</a></p>
  </div></main>
</body>
</html>
