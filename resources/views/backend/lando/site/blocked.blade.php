@extends('backend.layouts.app')

@section('content')
    @php $ip = $type === 'ip'; @endphp
    @include('backend.partials.page-head', ['title' => $ip ? 'Blocked IPs' : 'Blocked Areas',
        'sub' => $ip ? 'Visitors from these addresses see “Not available” instead of the website. The admin is never blocked.'
                     : 'Nobody can open these parts of the website: a path covers itself and everything under it. The home page and the admin can\'t be blocked.',
        'actions' => '<a class="btn cyan" href="'.route('lando.blocked.'.$kind.'.create').'">'.($ip ? 'Block an IP' : 'Block an Area').'</a>'])

    <div class="panel"><div class="table-wrap"><table class="table">
        <thead><tr><th>{{ $ip ? 'IP Address' : 'Area' }}</th><th>Reason</th><th>Until</th><th class="num">Turned Away</th><th>{{ $ip ? 'Last Visit' : 'Pages Covered' }}</th><th>Status</th><th></th></tr></thead>
        <tbody>@forelse ($blocks as $b)
            <tr><td class="mono">{{ $ip ? $b->value : '/'.$b->value }}</td>
                <td class="wrap">{{ $b->reason ?? '—' }}<div class="muted" style="font-size:.78rem">by {{ $b->user?->name ?? 'System' }}, {{ $b->created_at->format('n/j/Y') }}</div></td>
                <td class="nowrap">{{ $b->expires_at?->format('n/j/Y g:i A') ?? 'Until unblocked' }}</td>
                <td class="num">{{ number_format($b->hits) }}@if ($b->last_hit_at)<div class="muted" style="font-size:.78rem">last {{ $b->last_hit_at->diffForHumans() }}</div>@endif</td>
                <td>@if ($ip)@if ($s = $lastSeen[$b->value] ?? null)<a href="{{ route('lando.visitors', ['ip' => $b->value]) }}">{{ \Illuminate\Support\Carbon::parse($s->at)->format('n/j/Y') }}</a> <span class="muted">· {{ $s->n }}</span>@elseif (str_ends_with($b->value, '*'))<a href="{{ route('lando.visitors', ['ip' => $b->value]) }}">Search</a>@else<span class="muted">—</span>@endif
                    @else{{ $covers[$b->id] ?? 0 }} {{ Str::plural('page', $covers[$b->id] ?? 0) }}@endif</td>
                <td>@include('backend.partials.pill', $b->isInForce() ? ['text' => 'Blocked', 'tone' => 'bad'] : ['text' => $b->active ? 'Expired' : 'Off', 'tone' => ''])</td>
                <td class="actions"><a class="btn sm ghost" href="{{ route('lando.blocked.'.$kind.'.edit', $b) }}">Edit</a>
                    <form method="post" action="{{ route('lando.blocked.'.$kind.'.toggle', $b) }}" class="inline">@csrf<button class="btn sm ghost">{{ $b->active ? 'Unblock' : 'Block' }}</button></form>
                    <form method="post" action="{{ route('lando.blocked.'.$kind.'.destroy', $b) }}" class="inline" data-confirm="Remove {{ $b->value }}?|It comes off the list for good. Use Unblock to keep it for later.|Remove">@csrf @method('delete')<button class="btn sm ghost">Remove</button></form></td></tr>
        @empty <tr><td colspan="7" class="empty">Nothing blocked.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
