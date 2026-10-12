@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Update Sitemap', 'sub' => 'Rebuilds public/sitemap.xml from the published pages in Lando. It also rebuilds every Sunday at 3 AM (Crons).'])

    <div class="panel"><div class="panel-body">
        <dl class="kv">
            <dt>Sitemap</dt><dd>@if ($builtAt)<a href="{{ url('sitemap.xml') }}" target="_blank" rel="noopener">sitemap.xml</a> · {{ number_format($urls) }} pages@else Not built yet @endif</dd>
            <dt>Last Built</dt><dd>{{ $builtAt?->format('n/j/Y g:i A') ?? '—' }}</dd>
            <dt>Last Run</dt><dd>@if ($last){{ $last->started_at->format('n/j/Y g:i A') }} · {{ $last->status }}@if ($last->message) — {{ $last->message }}@endif @else — @endif</dd>
        </dl>
        <form method="post" action="{{ route('sheriff.sitemap.run') }}" style="margin-top:16px">@csrf<button class="btn cyan">Update Sitemap Now</button></form>
    </div></div>
@endsection
