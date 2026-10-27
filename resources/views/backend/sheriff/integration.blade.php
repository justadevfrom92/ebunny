@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => $i['name'], 'sub' => e($i['purpose']).' · '.view('backend.partials.pill', $i['configured'] ? ['text' => 'Configured', 'tone' => 'ok'] : ['text' => 'Not configured', 'tone' => 'warn'])->render(),
        'actions' => '<a class="btn cyan" href="'.route('sheriff.integrations.configure', request()->route('integration')).'">Configure</a>'])
    <div class="banner-note">Keys and passwords live in the server's <span class="mono">.env</span> file, never in the database or the code. Set them there and run <span class="mono">php artisan config:cache</span>; this page only shows whether each value is present.</div>

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Settings</h2></div><div class="panel-body">
            <dl class="kv" style="grid-template-columns:1fr auto">
                @foreach ($i['set'] as $env => $set)<dt class="mono">{{ $env }}</dt><dd @class(['secret-set' => $set, 'muted' => ! $set])>{{ $set ? 'set' : '—' }}</dd>@endforeach
            </dl>
        </div></div>
        <div class="panel"><div class="panel-head"><h2>Last 30 Days</h2></div><div class="panel-body">
            <dl class="kv">
                <dt>Calls</dt><dd>{{ number_format($stats->total ?? 0) }}</dd>
                <dt>Successful</dt><dd>{{ number_format($stats->ok ?? 0) }}@if ($stats->total) <span class="help">({{ round(100 * $stats->ok / $stats->total) }}%)</span>@endif</dd>
                <dt>Average Response</dt><dd>{{ $stats->avg_ms ? number_format($stats->avg_ms).' ms' : '—' }}</dd>
            </dl>
        </div></div>
    </div>

    <div class="panel"><div class="panel-head"><h2>Recent Calls</h2></div><div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Time</th><th>Action</th><th>Account</th><th>Status</th><th class="num">Response</th></tr></thead>
        <tbody>@forelse ($calls as $l)
            @php $u = \App\Support\Records::url('api_log', $l->id); @endphp<tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $l->id }}</b></a></td><td>{{ $l->created_at->format('n/j/Y g:i:s A') }}</td><td class="mono">{{ $l->action }}</td>
                <td>{{ $l->customer?->account ?? '—' }}</td>
                <td>@include('backend.partials.pill', ['text' => $l->status, 'tone' => str_starts_with($l->status, '2') ? 'ok' : 'bad'])</td>
                <td class="num">{{ $l->response_ms !== null ? number_format($l->response_ms).' ms' : '—' }}</td></tr>
        @empty <tr><td colspan="6" class="empty">No calls logged for {{ $i['name'] }}.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
