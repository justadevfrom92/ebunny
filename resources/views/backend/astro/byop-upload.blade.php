@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Upload BYOP Discounts', 'sub' => 'Update Build Your Own Plan add-on pricing from a CSV file. Nothing changes unless every row is valid.'])

    @if ($errors->has('file'))
        <div class="alert bad"><b>Nothing was saved.</b><ul style="margin:6px 0 0 18px">@foreach ($errors->get('file') as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="grid-2">
        <form method="post" action="{{ route('astro.byop.import') }}" enctype="multipart/form-data" class="panel">@csrf
            <div class="panel-head"><h2>Upload</h2></div>
            <div class="panel-body" style="display:flex;flex-direction:column;gap:14px">
                <input type="file" name="file" accept=".csv,text/csv" required aria-label="CSV file">
                <p class="help">Columns: <span class="mono">key, rate_adj, min_discount, max_discount</span>. A header row is optional; blank cells leave that value as is. Values are ¢/kWh; negative is a discount.</p>
                <div><button class="btn cyan">Upload &amp; Apply</button></div>
            </div>
        </form>
        <div class="panel"><div class="panel-head"><h2>Current Values</h2><span class="muted">copy as a starting file</span></div>
            <div class="panel-body"><pre class="mono" style="margin:0;white-space:pre-wrap;font-size:.8rem">key,rate_adj,min_discount,max_discount
@foreach ($products as $p){{ $p->key }},{{ number_format($p->rate_adj, 2, '.', '') }},{{ $p->min_discount !== null ? number_format($p->min_discount, 2, '.', '') : '' }},{{ $p->max_discount !== null ? number_format($p->max_discount, 2, '.', '') : '' }}
@endforeach</pre></div></div>
    </div>
@endsection
