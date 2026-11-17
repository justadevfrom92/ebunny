@extends('backend.layouts.app')

@section('crumb', '#'.$item->id)

@section('content')
    @include('backend.partials.page-head', ['title' => $item->summary, 'sub' => e($item->group).' · '.e($item->action).' · '.$item->created_at->format('F jS, Y @ g:i:s A'),
        'actions' => '<a class="btn ghost" href="'.route('admin.account.activity').'">Back to My Activity</a>'])

    <div class="panel"><div class="panel-head"><h2>Details</h2><span class="mono muted">#{{ $item->id }}</span></div><div class="panel-body"><dl class="kv">
        <dt>What</dt><dd>{{ $item->summary }}</dd>
        <dt>Type</dt><dd>{{ $item->group }}</dd>
        <dt>Action</dt><dd>{{ $item->action }}</dd>
        <dt>When</dt><dd>{{ $item->created_at->format('n/j/Y g:i:s A') }} <span class="muted">({{ $item->created_at->diffForHumans() }})</span></dd>
        @if ($item->customer)<dt>Account</dt><dd>@if (auth()->user()->hasPerm('corral'))<a href="{{ route('corral.customers.show', $item->customer) }}">{{ $item->customer->account }}</a>@else{{ $item->customer->account }}@endif · {{ $item->customer->name }}</dd>@endif
        @if ($item->ip)<dt>IP Address</dt><dd class="mono">{{ $item->ip }}</dd>@endif
    </dl></div></div>

    @if ($item->changes)
        <div class="panel"><div class="panel-head"><h2>Changes</h2></div><div class="table-wrap"><table>
            <thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead>
            <tbody>@foreach ($item->changes as $field => [$old, $new])
                <tr><td class="mono">{{ $field }}</td><td class="wrap">{{ is_scalar($old) || $old === null ? ($old ?? '—') : json_encode($old) }}</td><td class="wrap"><b>{{ is_scalar($new) || $new === null ? ($new ?? '—') : json_encode($new) }}</b></td></tr>
            @endforeach</tbody>
        </table></div></div>
    @endif
@endsection
