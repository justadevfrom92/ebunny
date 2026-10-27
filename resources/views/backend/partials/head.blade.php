<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" href="/shared/img/logo-mark.svg">
<link rel="stylesheet" href="/backend/admin.css">
{{-- Rows of more than 3 pills are turned into dropdowns by admin.js; hide them until then so they never flash as pills --}}
<script>document.documentElement.classList.add('js'); setTimeout(function () { document.documentElement.classList.add('pills-done'); }, 1500);</script>
<script src="/backend/admin.js" defer></script>
<script src="/shared/password-toggle.js" defer></script>
<script src="/shared/datepicker.js" defer></script>
@yield('head')
