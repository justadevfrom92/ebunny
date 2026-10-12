@extends('admin.layouts.plain')

@section('title', 'Admin')

@php $user = auth()->user(); @endphp

@section('content')
    @include('admin.partials.page-head', [
        'title' => 'Howdy, '.strtok($user->name, ' '),
        'sub' => 'Choose an app. Only the apps you have access to are shown.',
        'actions' => (Gate::allows('manage-apps') ? '<a class="btn cyan" href="'.route('apps.create').'">+ New Admin App</a>' : '')
            .'<a class="btn ghost" href="/" target="_blank" rel="noopener">View website</a>',
    ])
    @if ($denied)
        <div class="banner-note">You don't have access to {{ $denied }}. Ask an administrator to add it in Sheriff → Users.</div>
    @endif
    <div class="tiles">
        @foreach ($apps as $key => $a)
            @continue(! $user->hasPerm($key))
            <a class="tile" href="{{ $a['url'] }}">
                <span class="ico">@include('admin.partials.icon', ['name' => $a['icon']])</span>
                <h2>{{ $a['name'] }}</h2><p>{{ $a['desc'] }}</p><span class="open">Open {{ $a['name'] }} →</span>
            </a>
        @endforeach
        @if (! collect($apps)->keys()->contains(fn ($k) => $user->hasPerm($k)))
            <div class="panel" style="grid-column:1/-1"><div class="panel-body muted">You don't have access to any admin apps yet. Ask an administrator to set up your access in Sheriff → Users.</div></div>
        @endif
        @can('manage-apps')
            <a class="tile tile-new" href="{{ route('apps.create') }}">
                <span class="ico">+</span><h2>New Admin App</h2><p>Add your own app with a tile here and a sidebar of links.</p><span class="open">Create →</span>
            </a>
        @endcan
    </div>
@endsection
