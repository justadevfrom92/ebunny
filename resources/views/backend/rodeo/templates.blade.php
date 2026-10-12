@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Email Templates', 'sub' => 'Emails customers get. Corral\'s Send Email action and campaigns use these.',
        'actions' => '<a class="btn cyan" href="'.route('rodeo.templates.create', array_filter(['category' => is_numeric($only) ? $only : null])).'">New Template</a>'])
    <div class="actions" style="margin-bottom:14px"><a class="btn sm {{ $only ? 'ghost' : '' }}" href="{{ route('rodeo.templates') }}">All</a>@foreach ($categories as $c)<a class="btn sm {{ (string) $only === (string) $c->id ? '' : 'ghost' }}" href="{{ route('rodeo.templates', ['category' => $c->id]) }}"><span class="sv-dot" style="--dot:{{ $c->color }};margin-right:6px"></span>{{ $c->name }}</a>@endforeach<a class="btn sm {{ $only === 'none' ? '' : 'ghost' }}" href="{{ route('rodeo.templates', ['category' => 'none']) }}">Uncategorized</a></div>
    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>Template</th><th>Category</th><th>Subject</th><th class="num">Sent</th><th class="num">Campaigns</th><th>Last Test</th><th>Updated</th><th></th></tr></thead>
        <tbody>@forelse ($templates as $t)<tr class="click" data-href="{{ route('rodeo.templates.edit', $t) }}"><td><a href="{{ route('rodeo.templates.edit', $t) }}"><b>{{ $t->name }}</b></a></td>
            <td class="nowrap">@if ($t->category)<span class="sv-cat"><span class="sv-dot" style="--dot:{{ $t->category->color }}"></span>{{ $t->category->name }}</span>@else<span class="muted">—</span>@endif</td>
            <td>{{ $t->subject }}</td>
            <td class="num">@if ($n = $sent[$t->name] ?? 0)<a href="{{ route('rodeo.emails.sent', ['template' => $t->name]) }}">{{ number_format($n) }}</a>@else 0 @endif</td>
            <td class="num">{{ $t->campaigns_count }}</td><td>{{ $t->test_sends_max_created_at ? \Illuminate\Support\Carbon::parse($t->test_sends_max_created_at)->format('n/j/Y') : '—' }}@if ($t->test_sends_count)<span class="muted"> · {{ $t->test_sends_count }} {{ Str::plural('test', $t->test_sends_count) }}</span>@endif</td><td>{{ $t->updated_at->format('n/j/Y') }}</td><td class="actions"><a class="btn sm" href="{{ route('rodeo.templates.test', $t) }}">Send Test</a></td></tr>
        @empty <tr><td colspan="8" class="empty">No templates here.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
