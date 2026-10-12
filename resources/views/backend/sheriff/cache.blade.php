@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Cached Tables', 'sub' => 'A read-only copy of each admin page\'s table, saved when someone opens the page and it changed since the last copy, and a copy of a database table each time a record is added to it. The last '.\App\Models\TableSnapshot::KEEP.' copies of each are kept. Passwords, tokens and keys are never copied.'])

    <div class="actions" style="margin-bottom:14px">
        <a class="btn sm {{ $kind === 'page' ? '' : 'ghost' }}" href="{{ route('sheriff.cache') }}">Pages Visited ({{ $counts['page'] ?? 0 }})</a>
        <a class="btn sm {{ $kind === 'record' ? '' : 'ghost' }}" href="{{ route('sheriff.cache', ['kind' => 'record']) }}">New Records ({{ $counts['record'] ?? 0 }})</a>
        @if ($kind === 'page')<span class="muted" style="margin-left:8px">App:</span><a class="btn sm {{ $app ? 'ghost' : '' }}" href="{{ route('sheriff.cache') }}">All</a>@foreach ($apps as $a)<a class="btn sm {{ $app === $a ? '' : 'ghost' }}" href="{{ route('sheriff.cache', ['app' => $a]) }}">{{ \App\Support\AdminApps::get($a)['name'] ?? $a }}</a>@endforeach @endif
    </div>

    <div class="panel"><div class="table-wrap"><table class="table">
        <thead><tr><th>{{ $kind === 'page' ? 'Page' : 'Table' }}</th>@if ($kind === 'page')<th>App</th>@endif<th>Latest Copy</th><th class="num">Rows</th><th class="num">Copies</th><th></th></tr></thead>
        <tbody>@forelse ($rows as $r)
            <tr class="click" data-href="{{ route('sheriff.cache.show', $r) }}">
                <td><a href="{{ route('sheriff.cache.show', $r) }}"><b>{{ $r->title }}</b></a><div class="mono help">{{ $r->key }}</div></td>
                @if ($kind === 'page')<td>{{ \App\Support\AdminApps::get($r->app)['name'] ?? '—' }}</td>@endif
                <td class="nowrap">{{ $r->created_at->format('n/j/Y g:i A') }}<div class="help">{{ $r->user?->name ?? 'System' }}</div></td>
                <td class="num">{{ number_format($r->row_count) }}</td><td class="num">{{ $r->copies }}</td>
                <td class="actions"><a class="btn sm ghost" href="{{ route('sheriff.cache.show', $r) }}">View</a></td></tr>
        @empty <tr><td colspan="6" class="empty">{{ $kind === 'page' ? 'No pages cached yet. Copies appear as people use the admin.' : 'No records added yet. A copy is saved each time someone adds a record.' }}</td></tr> @endforelse</tbody>
    </table></div>
    @include('backend.partials.pager', ['p' => $rows])</div>
@endsection
