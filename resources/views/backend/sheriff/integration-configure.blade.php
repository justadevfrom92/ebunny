@extends('backend.layouts.app')

@section('crumb', 'Configure '.$i['name'])

@section('content')
    @include('backend.partials.page-head', ['title' => 'Configure '.$i['name'],
        'sub' => e($i['purpose']).' · '.view('backend.partials.pill', $i['configured'] ? ['text' => 'Configured', 'tone' => 'ok'] : ['text' => 'Not configured', 'tone' => 'warn'])->render(),
        'actions' => '<a class="btn ghost" href="'.route('sheriff.integrations.show', $i['key']).'">Calls</a><a class="btn ghost" href="'.route('sheriff.integrations.index').'">All APIs</a>'])

    @if ($r = session('test_result'))<div class="flash {{ $r['ok'] ? 'ok' : 'bad' }}" role="status" style="margin-bottom:16px"><b>{{ $r['ok'] ? 'Connected.' : 'Test failed.' }}</b> {{ $r['message'] }}@if ($r['ms'] !== null) <span class="muted">({{ number_format($r['ms']) }} ms)</span>@endif</div>@endif
    @if ($cached)<div class="banner-note" style="margin-bottom:16px">The settings cache is on: uploading clears it so the new values are used. Run <span class="mono">php artisan config:cache</span> again afterwards.</div>@endif

    <div class="stack">
        <div class="grid-2" style="align-items:start">
            <div class="panel"><div class="panel-head"><h2>Settings</h2><span class="muted">in the server's .env</span></div>
                <div class="table-wrap"><table><thead><tr><th>Setting</th><th>Status</th></tr></thead><tbody>
                    @foreach ($i['set'] as $env => $set)<tr><td class="mono">{{ $env }}</td><td>@include('backend.partials.pill', $set ? ['text' => 'Set', 'tone' => 'ok'] : ['text' => 'Not set', 'tone' => 'warn'])</td></tr>@endforeach
                </tbody></table></div>
                <div class="panel-body help">Values are never shown here, in history or in logs, only whether each one is set.</div>
            </div>

            <div class="panel"><div class="panel-head"><h2>Test Connection</h2></div>
                <div class="panel-body">
                    <p style="margin:0 0 14px">Makes the lightest read-only call to {{ $i['name'] }} with the saved settings, to check they work. Nothing is charged, sent or changed.</p>
                    <form method="post" action="{{ route('sheriff.integrations.test', $i['key']) }}">@csrf<button class="btn cyan">Test Connection</button></form>
                </div>
            </div>
        </div>

        <div class="panel"><div class="panel-head"><h2>Upload .env</h2></div>
            @can('configure')
                <form method="post" action="{{ route('sheriff.integrations.upload', $i['key']) }}" enctype="multipart/form-data" class="panel-body" style="display:grid;gap:12px;max-width:720px">@csrf
                    <p style="margin:0">Choose a <span class="mono">.env</span> file with {{ $i['name'] }}'s settings. Only these lines are used, and anything else in the file is ignored:</p>
                    <pre class="mono" style="margin:0;padding:10px 12px;background:var(--row);border-radius:6px;font-size:.82rem;overflow:auto">@foreach (array_keys($i['env']) as $env){{ $env }}=…
@endforeach</pre>
                    <div class="field"><label for="env_file">.env file</label><input id="env_file" name="env_file" type="file" required accept=".env,.txt,text/plain"></div>
                    <div class="actions"><button class="btn cyan">Upload Settings</button></div>
                </form>
            @else
                <div class="panel-body muted">Uploading settings needs the “Change API settings” right (Sheriff → Roles).</div>
            @endcan
        </div>

        <div class="panel"><div class="panel-head"><h2>Recent Tests</h2></div><div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>When</th><th>By</th><th>Result</th><th>Details</th><th class="num">Time</th></tr></thead>
            <tbody>@forelse ($tests as $t)
                @php $u = \App\Support\Records::url('integration_test', $t->id); @endphp<tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $t->id }}</b></a></td><td class="nowrap">{{ $t->created_at->format('n/j/Y g:i A') }}</td><td>{{ $t->user?->name ?? 'System' }}</td>
                    <td>@include('backend.partials.pill', $t->ok ? ['text' => 'Connected', 'tone' => 'ok'] : ['text' => 'Failed', 'tone' => 'bad'])</td>
                    <td class="wrap">{{ $t->message }}</td><td class="num">{{ $t->response_ms !== null ? number_format($t->response_ms).' ms' : '—' }}</td></tr>
            @empty <tr><td colspan="6" class="empty">Not tested yet.</td></tr> @endforelse</tbody>
        </table></div></div>
    </div>
@endsection
