@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'APIs', 'sub' => 'Third-party integrations.',
        'actions' => '<form method="post" action="'.route('sheriff.integrations.test-all').'" class="inline">'.csrf_field().'<button class="btn cyan">Test All Connections</button></form>'])
    <div class="banner-note">Keys and passwords live in the server's <span class="mono">.env</span> file, never in the database or the code. This screen only shows whether each value is set. Use <b>Configure</b> to upload a company's settings and test the connection.</div>
    <div class="grid-3">
        @foreach ($integrations as $key => $i)
            <div class="panel"><div class="panel-head"><h2><a href="{{ route('sheriff.integrations.show', $key) }}">{{ $i['name'] }}</a></h2>@include('backend.partials.pill', $i['configured'] ? ['text' => 'Configured', 'tone' => 'ok'] : ['text' => 'Not configured', 'tone' => 'warn'])</div>
                <div class="panel-body">
                    <p class="muted" style="margin-bottom:10px">{{ $i['purpose'] }}</p>
                    <dl class="kv" style="grid-template-columns:1fr auto">
                        @foreach ($i['set'] as $env => $set)<dt class="mono">{{ $env }}</dt><dd @class(['secret-set' => $set, 'muted' => ! $set])>{{ $set ? 'set' : '—' }}</dd>@endforeach
                    </dl>
                    @if ($t = $tests[$key] ?? null)<p class="help" style="margin-top:10px">Last test {{ $t->created_at->diffForHumans() }}: @include('backend.partials.pill', ['text' => $t->ok ? 'Connected' : 'Failed', 'tone' => $t->ok ? 'ok' : 'bad']) <span title="{{ $t->message }}">{{ Str::limit($t->message, 60) }}</span></p>@endif
                    <div class="actions" style="margin-top:12px"><form method="post" action="{{ route('sheriff.integrations.test', $key) }}" class="inline">@csrf<input type="hidden" name="back" value="list"><button class="btn sm cyan">Test Connection</button></form><a class="btn sm ghost" href="{{ route('sheriff.integrations.configure', $key) }}">Configure</a><a class="btn sm ghost" href="{{ route('sheriff.integrations.show', $key) }}">Calls</a></div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
