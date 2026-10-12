@extends('backend.layouts.app')

@section('crumb', 'Home')

@section('content')
    @include('backend.partials.page-head', [
        'title' => $app['name'],
        'sub' => e($app['desc']),
        'actions' => $canManage ? '<a class="btn ghost" href="'.route('apps.edit', $appKey).'">Edit App</a>' : null,
    ])
    @forelse ($app['menu'] as $heading => $items)
        <div class="panel"><div class="panel-head"><h2>{{ $heading }}</h2></div><div class="panel-body">
            <div class="tiles">
                @foreach ($items as $it)
                    <a class="tile" href="{{ $it['url'] }}" @if ($it['external']) target="_blank" rel="noopener" @endif>
                        <h2>{{ $it['label'] }}</h2><span class="open">{{ $it['external'] ? 'Open link ↗' : 'Open →' }}</span>
                    </a>
                @endforeach
            </div>
        </div></div>
    @empty
        <div class="panel empty">This app has no links yet.@if ($canManage) <a href="{{ route('apps.edit', $appKey) }}">Add some</a>.@endif</div>
    @endforelse
@endsection
