@extends('backend.layouts.app')

@section('crumb', 'Winner #'.$w->id)

{{-- One drawing winner: who, which month, the prize and when it was won, and that month's drawing. --}}
@section('content')
    @include('backend.partials.page-head', ['title' => ($month ? $month->format('F Y') : $monthRaw).' Winner', 'sub' => 'Winner #'.$w->id.($c ? ' · '.e($c->name) : ''),
        'actions' => '<a class="btn ghost btn-back" href="'.route('bounty.winners').'">Back to Drawing Winners</a>'
            .'<form method="post" action="'.route('bounty.winners.destroy', $w).'" class="inline" data-confirm="Remove this winner?|They are removed from Drawing Winners.|Remove">'.csrf_field().method_field('delete').'<button class="btn ghost">Remove Winner</button></form>'])

    <div style="display:flex;flex-direction:column;gap:20px">
        <div class="grid-4 stat-row">
            <div class="stat"><span>Prize</span><strong style="font-size:1.15rem">{{ $prize ?: '—' }}</strong></div>
            <div class="stat"><span>Date Won</span><strong style="font-size:1.15rem">{{ $drawn?->format('F j, Y') ?? '—' }}</strong></div>
            <div class="stat"><span>Entries That Month</span><strong>{{ $entries !== null ? number_format($entries) : '—' }}</strong></div>
            <div class="stat"><span>Their Entries</span><strong>{{ $theirEntries ?? '—' }}</strong></div>
        </div>

        <div class="panel"><div class="panel-head"><h2>Winner</h2></div><div class="panel-body"><dl class="kv">
            <dt>Account</dt><dd>@if ($c)@include('backend.partials.account-link', ['c' => $c])@else<span class="mono">{{ $account ?? '—' }}</span> <span class="help">(no account with this number)</span>@endif</dd>
            @if ($c)
                <dt>Name</dt><dd>{{ $c->name }}</dd>
                <dt>Email</dt><dd>{{ $c->email ?? '—' }}</dd>
                <dt>Phone</dt><dd>{{ $c->phone ?? '—' }}</dd>
                <dt>Address</dt><dd>{{ trim($c->address.($c->unit ? ' #'.$c->unit : '')) }}, {{ $c->city }}, TX {{ $c->zip }}</dd>
                <dt>Stars</dt><dd>{{ number_format($c->stars) }}</dd>
            @endif
            <dt>Month</dt><dd>@if ($month)<a href="{{ route('bounty.drawing', ['month' => $month->format('Y-m')]) }}">{{ $month->format('F Y') }}</a>@else {{ $monthRaw }} @endif</dd>
            <dt>Prize</dt><dd>{{ $prize ?: '—' }}</dd>
            <dt>Date Won</dt><dd>{{ $drawn?->format('F j, Y') ?? '—' }}</dd>
        </dl></div></div>

        @if ($note)
            <div class="panel"><div class="panel-head"><h2>Note on the Account</h2></div><div class="panel-body">
                <p style="margin:0">{{ $note->body }}</p><p class="help" style="margin:6px 0 0">{{ $note->author ?? 'System' }} · {{ $note->created_at->format('n/j/Y g:i A') }}</p>
            </div></div>
        @endif
    </div>
@endsection
