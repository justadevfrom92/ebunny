@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'ETL Feeds', 'sub' => 'Files that come in from, or go out to, other companies on a schedule. Open a feed for every run and its errors.'])

    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>Feed</th><th>Direction</th><th>Schedule</th><th>Last Run</th><th>Last Status</th><th>Last 7 Days</th></tr></thead>
        <tbody>@foreach ($feeds as $key => $feed)
            @php $l = $last['etl:'.$key] ?? null; $w = $week['etl:'.$key] ?? null; @endphp
            <tr class="click" data-href="{{ route('walker.etl.show', $key) }}">
                <td><a href="{{ route('walker.etl.show', $key) }}"><b>{{ $feed['name'] }}</b></a><div class="help mono">{{ $feed['file'] }}</div></td>
                <td>@include('backend.partials.pill', ['text' => $feed['direction'] === 'In' ? 'Inbound' : 'Outbound', 'tone' => $feed['direction'] === 'In' ? 'info' : ''])<div class="help">{{ $feed['direction'] === 'In' ? 'from' : 'to' }} {{ $feed['party'] }}</div></td>
                <td class="nowrap">{{ $feed['schedule'] }}</td>
                <td>{{ $l?->started_at->format('n/j g:i A') ?? '—' }}@if ($l)<div class="help">{{ $l->started_at->diffForHumans() }}</div>@endif</td>
                <td>@if ($l)@include('backend.partials.pill', ['text' => $l->status, 'tone' => ['OK' => 'ok', 'Warning' => 'warn', 'Failed' => 'bad'][$l->status] ?? ''])@else — @endif</td>
                <td>{{ $w->runs ?? 0 }} {{ Str::plural('run', $w->runs ?? 0) }} @if ($w?->failed || $w?->warned)<div>@if ($w->failed)<span class="pill bad">{{ $w->failed }} failed</span>@endif @if ($w->warned)<span class="pill warn">{{ $w->warned }} warning</span>@endif</div>@endif</td>
            </tr>
        @endforeach</tbody>
    </table></div></div>
@endsection
