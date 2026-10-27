@extends('backend.layouts.app')

@section('crumb', $title)

{{-- A ticket page like the original Corral: the account ticket or one of its log tickets. --}}
@section('content')
    @include('backend.partials.page-head', [
        'title' => $title,
        'sub' => 'created: '.$created->format('F jS, Y @ g:i:s A').' · id <span class="mono">'.e($ticketId).'</span> · '.e($c->name),
        'actions' => '<a class="btn ghost" href="'.route('corral.customers.show', $c).'">Back to account</a>'
            .($log ? '<a class="btn ghost" href="'.route('corral.customers.ticket', $c).'">Account ticket</a>' : ''),
    ])

    {{-- Two views of the same items (no second query): grouped by item type, or one timeline by date and time --}}
    <div class="chips small" data-ticket-views role="tablist" aria-label="Ticket view">
        <button type="button" class="chip-link on" data-view="grouped" role="tab" aria-selected="true">By Item Type</button>
        <button type="button" class="chip-link" data-view="timeline" role="tab" aria-selected="false">Timeline</button>
    </div>

    <div data-view-pane="timeline" hidden>
        @php
            $timeline = $items->map(fn ($i) => ['at' => $i->created_at, 'id' => $i->id, 'what' => $i->model, 'summary' => $i->summary, 'who' => $i->user?->name ?? 'System', 'url' => route('corral.history.show', $i)])
                ->concat(collect($dataItems)->map(fn ($i) => ['at' => $i['created'], 'id' => $i['id'], 'what' => $i['label'], 'summary' => $i['summary'], 'who' => '', 'url' => $i['url']]))
                ->sortByDesc(fn ($e) => $e['at']->timestamp)->values();
        @endphp
        <div class="panel"><div class="panel-head"><h2>Timeline</h2><span class="muted">{{ $timeline->count() }} items, newest first</span></div>
            <div class="table-wrap"><table class="hist tl-ticket">
                <tbody>@forelse ($timeline->groupBy(fn ($e) => $e['at']->format('l, F j, Y')) as $day => $events)
                    <tr class="tl-daterow"><th colspan="4">{{ $day }}</th></tr>
                    @foreach ($events as $e)
                        <tr class="click" data-href="{{ $e['url'] }}"><td style="width:10ch" class="nowrap">{{ $e['at']->format('g:i:s A') }}</td>
                            <td style="width:22ch"><a class="mono" href="{{ $e['url'] }}">{{ $e['what'] }}</a></td><td class="wrap">{{ $e['summary'] }}</td><td class="muted" style="width:14ch">{{ $e['who'] }}</td></tr>
                    @endforeach
                @empty <tr><td class="empty">No items on this ticket yet.</td></tr> @endforelse</tbody>
            </table></div>
        </div>
    </div>

    <div data-view-pane="grouped" style="display:flex;flex-direction:column;gap:20px">
    @forelse ($groups as $model => $rows)
        <div class="panel">
            <div class="panel-head"><h2><span class="muted" style="font-weight:400">{{ $rows->count() }}</span> <span class="mono">{{ $model }}</span></h2></div>
            <div class="table-wrap"><table class="hist">
                <tbody>@foreach ($rows as $i)
                    <tr class="click" data-href="{{ route('corral.history.show', $i) }}">
                        <td class="mono" style="width:9ch"><a href="{{ route('corral.history.show', $i) }}">{{ $i->id }}</a></td>
                        <td style="width:19ch;white-space:nowrap">{{ $i->created_at->format('Y-m-d H:i:s') }}</td>
                        <td>{{ $i->summary }}</td>
                        <td class="muted" style="width:14ch">{{ $i->user?->name ?? 'System' }}</td>
                        <td class="actions" style="width:10ch">@if ($url = $i->recordUrl())<a class="btn sm ghost" href="{{ $url }}">Open</a>@endif</td>
                    </tr>
                @endforeach</tbody>
            </table></div>
        </div>
    @empty
        <div class="panel empty">No items on this ticket yet.</div>
    @endforelse
    </div>

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Actions</h2></div><div class="panel-body actions">
            <a class="btn ghost" href="{{ route('corral.customers.show', $c) }}">Open Account</a>
            <a class="btn ghost" href="{{ route('corral.customers.contact-log', $c) }}">Contact Log</a>
            <a class="btn ghost" href="{{ route('corral.customers.api-log', $c) }}">API Log</a>
        </div></div>
        <div class="panel"><div class="panel-head"><h2>Queues</h2></div><div class="panel-body">
            @forelse ($currentQueues as $q)<span class="pill info mono">{{ $q->queue }}</span> @empty<span class="muted">{{ $log ? 'Log tickets are not queued.' : 'Not in any queue.' }}</span>@endforelse
        </div></div>
    </div>

    <div class="panel"><div class="panel-head"><h2>Process Logs</h2></div>
        <div class="table-wrap"><table class="hist">
            <thead><tr><th>ID</th><th>Entered</th><th>Queue</th><th>Left</th><th>Note</th></tr></thead>
            <tbody>@forelse ($processLogs as $p)
                @php $u = \App\Support\Records::url('queue_log', $p->id); @endphp<tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $p->id }}</b></a></td><td>{{ $p->entered_at->format('n/j/Y h:i:s A') }}</td><td class="mono">queuelog_{{ \Illuminate\Support\Str::snake(\Illuminate\Support\Str::after($p->queue, 'Queue')) }}s</td>
                    <td>{{ $p->exited_at?->format('n/j/Y h:i:s A') ?? 'current' }}</td><td>{{ $p->note }}</td></tr>
            @empty <tr><td colspan="5" class="empty">No process logs.</td></tr> @endforelse</tbody>
        </table></div>
    </div>

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Parent Tickets</h2></div>
            <div class="table-wrap"><table><tbody>@forelse ($parents as $p)
                <tr><td class="mono"><a href="{{ $p['url'] }}">{{ $p['id'] }}</a></td><td>{{ $p['created']->format('Y-m-d H:i:s') }}</td><td>{{ $p['title'] }}</td></tr>
            @empty <tr><td class="empty">None — this is the account ticket.</td></tr> @endforelse</tbody></table></div>
        </div>
        <div class="panel"><div class="panel-head"><h2>Child Tickets</h2></div>
            <div class="table-wrap"><table><tbody>@forelse ($childTickets as $t)
                <tr class="click" data-href="{{ route('corral.customers.log', [$c, $t['key']]) }}">
                    <td><a href="{{ route('corral.customers.log', [$c, $t['key']]) }}">{{ $t['title'] }}</a></td>
                    <td class="num">{{ $t['count'] }}</td><td>{{ \Illuminate\Support\Carbon::parse($t['first'])->format('Y-m-d H:i:s') }}</td></tr>
            @empty <tr><td class="empty">None.</td></tr> @endforelse</tbody></table></div>
        </div>
    </div>

    {{-- The original items on this ticket, each opening its own data item page --}}
    <div class="panel"><div class="panel-head"><h2>Child Items</h2><span class="muted">{{ $dataItems->count() }}</span></div>
        <div class="table-wrap"><table class="hist"><tbody>@forelse ($dataItems as $i)
            <tr class="click" data-href="{{ $i['url'] }}"><td style="width:22ch"><a class="mono" href="{{ $i['url'] }}">{{ $i['id'] }}</a><br><span class="muted">{{ $i['created']->format('Y-m-d H:i:s') }}</span></td>
                <td style="width:18ch">{{ $i['label'] }}</td><td>{{ $i['summary'] }}</td><td class="mono muted">{{ $i['model'] }}</td></tr>
        @empty <tr><td class="empty">No items on this ticket.</td></tr> @endforelse</tbody></table></div>
    </div>
@endsection
