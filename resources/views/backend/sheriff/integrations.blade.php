@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'APIs', 'sub' => 'Third-party integrations.'])
    <div class="banner-note">Keys and passwords live in the server's <span class="mono">.env</span> file, never in the database or the code. This screen only shows whether each value is set. Use <b>Configure</b> to upload a company's settings and test the connection.</div>
    <div class="grid-3">
        @foreach ($integrations as $key => $i)
            <div class="panel"><div class="panel-head"><h2><a href="{{ route('sheriff.integrations.show', $key) }}">{{ $i['name'] }}</a></h2>@include('backend.partials.pill', $i['configured'] ? ['text' => 'Configured', 'tone' => 'ok'] : ['text' => 'Not configured', 'tone' => 'warn'])</div>
                <div class="panel-body">
                    <p class="muted" style="margin-bottom:10px">{{ $i['purpose'] }}</p>
                    <dl class="kv" style="grid-template-columns:1fr auto">
                        @foreach ($i['set'] as $env => $set)<dt class="mono">{{ $env }}</dt><dd @class(['secret-set' => $set, 'muted' => ! $set])>{{ $set ? 'set' : '—' }}</dd>@endforeach
                    </dl>
                    <div class="actions" style="margin-top:12px"><a class="btn sm cyan" href="{{ route('sheriff.integrations.configure', $key) }}">Configure</a><a class="btn sm ghost" href="{{ route('sheriff.integrations.show', $key) }}">Calls</a></div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
