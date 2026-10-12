@extends('backend.layouts.app')

@section('crumb', 'Cached: '.$s->title)

@section('content')
    @include('backend.partials.page-head', ['title' => $s->title,
        'sub' => ($s->kind === 'page' ? 'Page <span class="mono">'.e($s->key).'</span>' : 'Table <span class="mono">'.e($s->key).'</span>').' · copied '.$s->created_at->format('l, F j, Y g:i:s A').' by '.e($s->user?->name ?? 'System'),
        'actions' => '<a class="btn ghost" href="'.route('sheriff.cache', $s->kind === 'record' ? ['kind' => 'record'] : []).'">All Cached Tables</a>'])

    <div class="banner-note" style="margin-bottom:16px">A read-only copy: links and buttons are removed. {{ $s->kind === 'page' ? 'It shows the table exactly as it looked then; open the page itself for the live version.' : 'The newest 25 rows of '.number_format($s->row_count).' when record '.$s->record_id.' was added, which is highlighted.' }}</div>

    <div class="it-grid" style="grid-template-columns:minmax(0,3fr) minmax(0,1fr)">
        <div class="panel"><div class="table-wrap">
            @if ($s->kind === 'page')
                <div class="cached-table">{!! $s->html !!}</div>
            @else
                <table class="table"><thead><tr>@foreach ($s->columns as $col)<th>{{ $col }}</th>@endforeach</tr></thead>
                    <tbody>@foreach ($s->rows as $row)<tr @class(['row-new' => (string) ($row['id'] ?? '') === (string) $s->record_id])>@foreach ($s->columns as $col)<td>{{ $row[$col] ?? '' }}</td>@endforeach</tr>@endforeach</tbody></table>
            @endif
        </div></div>
        <div class="panel"><div class="panel-head"><h2>Copies</h2><span class="muted">{{ $versions->count() }}</span></div>
            <div class="table-wrap"><table><tbody>@foreach ($versions as $v)
                <tr @class(['row-new' => $v->id === $s->id])><td class="nowrap">@if ($v->id === $s->id)<b>{{ $v->created_at->format('n/j/Y g:i A') }}</b>@else<a href="{{ route('sheriff.cache.show', $v) }}">{{ $v->created_at->format('n/j/Y g:i A') }}</a>@endif<div class="help">{{ $v->user?->name ?? 'System' }} · {{ number_format($v->row_count) }} rows</div></td></tr>
            @endforeach</tbody></table></div>
        </div>
    </div>
@endsection
