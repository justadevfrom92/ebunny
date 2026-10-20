@extends('backend.layouts.app')

@section('crumb', $model.' '.$r->getKey())

{{-- One original Corral data item: corral/data/item/{id}/{Model} --}}
@section('content')
    @php
        $ticketUrl = $parents[0]['url'] ?? ($c ? route('corral.customers.ticket', $c) : null);
        $labels = config('items.actions');
        $accountLevel = in_array($d['source'], ['person', 'postal', 'login', 'plan'], true);
        $canDelete = auth()->user()->can('delete');
    @endphp
    @include('backend.partials.page-head', ['title' => $model,
        'sub' => 'id <span class="mono">'.$r->getKey().'</span>'.($c ? ' · account <span class="mono">'.e($c->account).'</span> · '.e($c->name) : ''),
        'actions' => ($ticketUrl ? '<a class="btn ghost" href="'.$ticketUrl.'">Back to ticket</a>' : '').($c ? '<a class="btn ghost" href="'.route('corral.customers.show', $c).'">Back to account</a>' : '')])

    @if (session('item_notice'))<div class="banner-note" role="status" style="margin-bottom:16px">{{ session('item_notice') }}</div>@endif

    <div class="it-grid">
        <div class="panel">
            <div class="panel-head it-dark"><h2>{{ $title }} <span class="it-created">--- created: {{ $created->format('F jS, Y') }} @ {{ $created->format('g:i:s A') }}</span></h2></div>
            <div class="table-wrap"><table class="it-fields">
                <tbody>@foreach ($values as $field => $value)
                    <tr><td>{{ $field }}</td><td>{{ $value }}</td></tr>
                @endforeach</tbody>
            </table></div>
            @if ($ticketUrl)<div class="panel-body"><a class="btn ghost" href="{{ $ticketUrl }}">‹ Back to ticket</a></div>@endif
        </div>

        <div class="it-side">
            @if ($r instanceof \App\Models\ContactLog)
                {{-- An email or text: how it ended (Resolved, Incomplete…) --}}
                <div class="panel"><div class="panel-head"><h2>Resolution</h2>@include('backend.partials.resolution-pill', ['value' => $r->resolution])</div>
                    <div class="panel-body">@include('backend.corral._resolution', ['type' => 'contact', 'id' => $r->id, 'value' => $r->resolution, 'by' => $r->resolvedBy?->name, 'at' => $r->resolved_at])</div></div>
            @endif
            <div class="panel">
                <div class="panel-head"><h2>Actions</h2></div>
                <div class="panel-body it-actions">
                    @foreach ($d['actions'] as $a)
                        @if ($a === 'edit')<a class="btn cyan" href="{{ route('corral.items.edit', [$r->getKey(), $model]) }}">Edit</a>
                        @elseif ($a === 'delete')
                            @if ($accountLevel)
                                <span class="it-delete"><button type="button" class="btn red" disabled title="Part of the account itself: change it with Edit, or delete the whole account">{{ $labels['delete'] }}</button></span>
                            @elseif ($canDelete)
                                <form method="post" action="{{ route('corral.items.destroy', [$r->getKey(), $model]) }}" class="inline it-delete" data-confirm="PERMANENTLY DELETE?|{{ $model }} {{ $r->getKey() }} is removed for good. This can't be undone.|Delete">@csrf @method('delete')<button class="btn red">{{ $labels['delete'] }}</button></form>
                            @endif
                        @else
                            <form method="post" action="{{ route('corral.items.act', [$r->getKey(), $model, $a]) }}" class="inline" @if (in_array($a, ['resend', 'duplicate', 'mark_deposit', 'link_accounts', 'cancel_rebill'], true)) data-confirm="{{ $labels[$a] }}?|Are you sure?|{{ $labels[$a] }}" @endif>@csrf<button class="btn {{ in_array($a, ['resend', 'cancel_rebill'], true) ? 'warn' : 'ghost' }}">{{ $labels[$a] }}</button></form>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="panel">
                @php
                    $entries = collect($events)->map(fn ($e) => $e + ['user' => null, 'url' => null])
                        ->merge($logs->map(fn ($l) => ['at' => $l->created_at, 'action' => $l->action, 'text' => $l->summary, 'user' => $l->user?->name ?? 'System', 'url' => route('corral.history.show', $l)]))
                        ->sortByDesc('at')->values();
                @endphp
                <div class="panel-head"><h2>Process Logs</h2><span class="muted">{{ $entries->count() }}</span></div>
                <div class="panel-body it-logs">
                    @forelse ($entries as $l)
                        <div><b>{{ $l['at']->format('Y-m-d H:i:s') }} - {{ $l['action'] }}</b><div class="it-log">@if ($c)Ticket <a href="{{ route('corral.customers.ticket', $c) }}" class="mono">{{ $c->ticket }}</a><br>@endif<big>{{ $l['text'] }}</big>@if ($l['url']) <span class="muted">· {{ $l['user'] }} · <a href="{{ $l['url'] }}">entry</a></span>@endif</div></div>
                    @empty <p class="muted" style="margin:0">Nothing logged for this item yet.</p> @endforelse
                </div>
            </div>

            <div class="panel">
                <div class="panel-head"><h2>Parent Tickets</h2></div>
                <div class="table-wrap"><table><tbody>@forelse ($parents as $p)
                    <tr><td>@if ($p['url'])<a class="mono" href="{{ $p['url'] }}">{{ $p['id'] }}</a>@else<span class="mono">{{ $p['id'] }}</span>@endif<br><span class="muted">{{ $p['created']->format('Y-m-d H:i:s') }}</span></td><td>{{ $p['a'] }}</td><td>{{ $p['b'] }}</td></tr>
                @empty <tr><td class="empty">None.</td></tr> @endforelse</tbody></table></div>
            </div>
            <div class="panel"><div class="panel-head"><h2>Child Tickets</h2></div><div class="panel-body muted">None.</div></div>
            <div class="panel">
                <div class="panel-head"><h2>Other {{ $model }} on this account</h2><span class="muted">{{ $siblings->count() }}</span></div>
                <div class="table-wrap"><table><tbody>@forelse ($siblings as $s)
                    <tr class="click" data-href="{{ route('corral.items.show', [$s->getKey(), $model]) }}"><td><a class="mono" href="{{ route('corral.items.show', [$s->getKey(), $model]) }}">{{ $s->getKey() }}</a><br><span class="muted">{{ \App\Support\Items::created($s)->format('Y-m-d H:i:s') }}</span></td><td>{{ $d['label'] }}</td><td>{{ \App\Support\Items::summary($model, $s) }}</td></tr>
                @empty <tr><td class="empty">None.</td></tr> @endforelse</tbody></table></div>
            </div>
        </div>
    </div>
@endsection
