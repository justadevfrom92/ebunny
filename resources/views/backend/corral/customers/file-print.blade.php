<!doctype html>
<html lang="{{ request('lang', 'en') }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $doc['title'] }} — {{ $c->account }}</title>
<style>
  body { font-family: Georgia, 'Times New Roman', serif; color: #111; background: #fff; margin: 0; }
  .sheet { max-width: 7.5in; margin: 0 auto; padding: .6in .5in; }
  .brand { font-family: system-ui, sans-serif; font-weight: 700; letter-spacing: .04em; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; }
  .brand small { font-weight: 400; color: #555; }
  h2 { font-size: 1.4rem; margin: 0 0 14px; }
  p { line-height: 1.5; margin: 0 0 10px; }
  .cust-doc-table { border-collapse: collapse; margin-top: 12px; width: 100%; }
  .cust-doc-table th, .cust-doc-table td { border: 1px solid #999; padding: 6px 10px; text-align: left; }
  .cust-doc-table th { width: 40%; background: #f2f2f2; }
  .no-print { font-family: system-ui, sans-serif; text-align: center; padding: 10px; background: #eef6fb; }
  @media print { .no-print { display: none; } .sheet { padding: 0; } }
</style>
</head>
<body>
<div class="no-print"><button onclick="window.print()">Print</button></div>
<div class="sheet">
    <div class="brand"><span>{{ config('brand.name') }}</span><small>{{ $c->name }} · {{ $c->account }} · {{ now()->format('n/j/Y') }}</small></div>
    @include('backend.corral.customers._document', ['doc' => $doc])
</div>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script>
</body>
</html>
