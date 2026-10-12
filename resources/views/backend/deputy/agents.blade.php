@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Agents', 'sub' => 'Each agent does one job and runs on one model, with a fallback model if that one can\'t answer.',
        'actions' => '<a class="btn cyan" href="'.route('deputy.agents.create').'">New Agent</a>'])
    <div class="panel"><div class="table-wrap"><table class="table">
        <thead><tr><th>Agent</th><th>Kind</th><th>Model</th><th>Fallback</th><th class="num">Conversations / Runs</th><th></th></tr></thead>
        <tbody>@forelse ($agents as $a)
            <tr><td><a href="{{ route('deputy.agents.edit', $a) }}"><b>{{ $a->name }}</b></a> @include('backend.partials.pill', $a->active ? ['text' => 'Active', 'tone' => 'ok'] : ['text' => 'Paused', 'tone' => ''])<div class="help">{{ $a->activityLabel() }}@if ($a->description) · {{ $a->description }}@endif</div></td>
                <td>@if ($a->talksToCustomers()){{ $a->activity === 'sms' ? 'Texts' : 'My Account chat' }}@else Automation<div class="help mono">{{ $a->schedule }}</div>@endif</td>
                <td>@include('backend.deputy._model', ['m' => $a->model])</td><td>@include('backend.deputy._model', ['m' => $a->fallback])</td>
                @if ($a->talksToCustomers())
                    <td class="num"><a href="{{ route('deputy.conversations', ['agent' => $a->id]) }}">{{ number_format($a->conversations_count) }}</a><div class="help">{{ $a->conversations_max_started_at ? 'last '.\Illuminate\Support\Carbon::parse($a->conversations_max_started_at)->format('n/j/Y') : 'none yet' }}</div></td>
                @else
                    <td class="num"><a href="{{ route('deputy.automations', ['agent' => $a->id]) }}">{{ number_format($a->runs_count) }} runs</a><div class="help">{{ $a->runs_max_started_at ? 'last '.\Illuminate\Support\Carbon::parse($a->runs_max_started_at)->format('n/j g:i A') : 'none yet' }}</div></td>
                @endif
                <td class="actions"><a class="btn sm ghost" href="{{ route('deputy.agents.edit', $a) }}">Edit</a><a class="btn sm" href="{{ route('deputy.agents.test', $a) }}">Test</a></td></tr>
        @empty <tr><td colspan="6" class="empty">No agents yet.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
