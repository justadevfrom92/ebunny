@extends('backend.layouts.app')

@section('crumb', $u->name.' · History')

@section('content')
    @include('backend.partials.page-head', [
        'title' => $u->name.' — History',
        'sub' => e($u->email).' · '.e($u->role?->name).' · '.number_format($total).' entries',
        'actions' => '<a class="btn ghost" href="'.route('sheriff.users.index').'">All Users</a>',
    ])
    @include('backend.history._counts', ['counts' => $counts, 'filters' => $filters])
    <div class="panel">
        <div class="panel-head"><h2>Activity</h2>
            @if ($filters)<a class="btn sm ghost" href="{{ route('sheriff.users.history', $u) }}">Clear filter ({{ $filters['hmodel'] ?? $filters['hgroup'] }})</a>@endif
        </div>
        @include('backend.history._list', ['items' => $items, 'route' => 'sheriff.history.show', 'showAccount' => true])
        @if ($items->hasPages())
            <div class="pager">
                @if ($items->previousPageUrl())<a href="{{ $items->previousPageUrl() }}">← Newer</a>@endif
                <span class="on">Page {{ $items->currentPage() }} of {{ $items->lastPage() }}</span>
                @if ($items->nextPageUrl())<a href="{{ $items->nextPageUrl() }}">Older →</a>@endif
            </div>
        @endif
    </div>
@endsection
