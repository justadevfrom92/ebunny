@extends('admin.layouts.app')

@section('content')
    @include('admin.partials.page-head', [
        'title' => 'Pages for '.$site->name,
        'sub' => number_format($pages->count()).' pages on <span class="mono">'.e($site->domain).'</span>. Sub-pages are indented under their parent.',
        'actions' => (($sitemap = app_route('pages.sitemap')) ? '<form method="post" action="'.$sitemap.'" class="inline">'.csrf_field().'<button class="btn ghost">Rebuild Sitemap</button></form>' : '')
            .'<a class="btn cyan" href="'.app_route('pages.create', ['site' => $site->id]).'">Add a Page</a>',
    ])
    @if ($sites->count() > 1)
        <div class="actions" style="margin-bottom:14px"><span class="muted">Site:</span>@foreach ($sites as $s)<a class="btn sm {{ $s->id === $site->id ? '' : 'ghost' }}" href="{{ app_route('pages.index', ['site' => $s->id]) }}">{{ $s->name }}</a>@endforeach</div>
    @endif
    <div class="panel"><div class="table-wrap"><table class="table pages-table">
        <thead><tr><th>Page</th><th>Title</th><th>Layout</th><th>Status</th><th class="num">Views this week</th><th>Right now</th><th>Updated</th><th></th></tr></thead>
        <tbody>@foreach ($pages as $p)
            @php
                $depth = $p->path === '/' ? 0 : substr_count($p->path, '/');
                $views = $traffic['views'][$p->id] ?? 0;
                $online = $traffic['online'][$p->id] ?? 0;
                $editing = $traffic['editing'][$p->id] ?? null;
                $blocked = $traffic['blocked']->first(fn ($b) => $b->matchesPath($p->path));
            @endphp
            <tr class="click" data-href="{{ app_route('pages.edit', $p) }}">
                <td style="padding-left:{{ 12 + $depth * 22 }}px" class="nowrap">@if ($depth)<span class="muted">└ </span>@endif<a href="{{ app_route('pages.edit', $p) }}" class="mono">{{ $p->path === '/' ? '/' : '/'.$p->path }}</a> <span class="help mono">#{{ $p->id }}</span>
                    @if ($p->redirect)<div class="help">redirects to {{ $p->redirect }}</div>@endif</td>
                <td class="wrap">{{ $p->title }}</td>
                <td>{{ $p->file ? 'Built-in: '.$p->file : ($p->template?->name ?? 'No template') }}@if ($p->components_count)<div class="help">{{ $p->components_count }} {{ Str::plural('component', $p->components_count) }}</div>@endif</td>
                <td>@include('admin.partials.pill', ['text' => $p->status, 'tone' => $p->status === 'Published' ? 'ok' : 'warn'])@if ($blocked)<br>@include('admin.partials.pill', ['text' => 'Blocked', 'tone' => 'bad'])@endif</td>
                <td class="num">{{ number_format($views) }}</td>
                <td>@if ($online)@include('admin.partials.pill', ['text' => $online.' on it', 'tone' => 'ok'])@endif @if ($editing)<div class="help">Editing: {{ collect($editing)->pluck('name')->implode(', ') }}</div>@endif @if (! $online && ! $editing)<span class="muted">—</span>@endif</td>
                <td class="nowrap">{{ $p->updated_at?->format('n/j/Y') }}</td>
                <td class="actions"><a class="btn sm ghost" href="{{ app_route('pages.edit', $p) }}">Edit</a>@if ($url = app_route('pages.activity', $p))<a class="btn sm ghost" href="{{ $url }}">Activity</a>@endif</td></tr>
        @endforeach</tbody>
    </table></div></div>
@endsection
