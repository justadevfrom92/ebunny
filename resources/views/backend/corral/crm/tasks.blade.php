@extends('backend.layouts.app')

{{-- Follow-ups: what's overdue, due today and coming up, for you or for everyone --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'Follow-ups', 'sub' => 'Calls, emails and visits promised to leads and partners. Done takes it off the list; the lead\'s next follow-up moves to the next one.'])
    <div class="chips small" style="margin-bottom:14px"><a href="{{ route('corral.crm.tasks', ['who' => 'me']) }}" @class(['chip-link', 'on' => ! $everyone])>Mine</a><a href="{{ route('corral.crm.tasks', ['who' => 'all']) }}" @class(['chip-link', 'on' => $everyone])>Everyone</a></div>

    @foreach (['Overdue' => $overdue, 'Due Today' => $today, 'Coming Up' => $upcoming] as $title => $list)
        <div class="panel"><div class="panel-head"><h2>{{ $title }}</h2><span class="muted">{{ $list->count() }}</span></div><div class="table-wrap"><table>
            <thead><tr><th>Due</th><th>Follow-up</th><th>Lead or Partner</th><th>Set By</th><th></th></tr></thead>
            <tbody>@forelse ($list as $t)
                @php $url = $t->lead ? route('corral.crm.leads.show', $t->lead) : ($t->partner ? route('corral.crm.partners.show', $t->partner) : null); @endphp
                <tr @if ($url) class="click" data-href="{{ $url }}" @endif><td class="nowrap"><span @class(['bad-text' => $title === 'Overdue'])>{{ $t->due_on->format('D n/j') }}</span>@if ($title === 'Overdue')<div class="help">{{ $t->due_on->diffForHumans() }}</div>@endif</td>
                    <td class="wrap"><b>{{ $t->subject }}</b>@if ($t->body)<div class="help">{{ Str::limit($t->body, 120) }}</div>@endif</td>
                    <td>@if ($url)<a href="{{ $url }}">{{ $t->lead?->displayName() ?? $t->partner?->name }}</a>@endif @if ($t->lead)<div class="help">{{ $t->lead->stageLabel() }}</div>@endif</td>
                    <td>{{ $t->user?->name }}</td>
                    <td class="actions"><form method="post" action="{{ route('corral.crm.tasks.complete', $t) }}" class="inline">@csrf<button class="btn sm">Done</button></form></td></tr>
            @empty <tr><td colspan="5" class="empty">{{ $title === 'Overdue' ? 'Nothing overdue.' : 'Nothing here.' }}</td></tr> @endforelse</tbody>
        </table></div></div>
    @endforeach

    <div class="panel"><div class="panel-head"><h2>Done in the Last 2 Weeks</h2></div><div class="table-wrap"><table>
        <thead><tr><th>Done</th><th>Follow-up</th><th>Lead or Partner</th><th>By</th><th></th></tr></thead>
        <tbody>@forelse ($done as $t)
            @php $url = $t->lead ? route('corral.crm.leads.show', $t->lead) : ($t->partner ? route('corral.crm.partners.show', $t->partner) : null); @endphp
            <tr @if ($url) class="click" data-href="{{ $url }}" @endif><td class="nowrap">{{ $t->done_at->format('n/j g:i A') }}</td><td class="wrap">{{ $t->subject }}</td><td>@if ($url)<a href="{{ $url }}">{{ $t->lead?->displayName() ?? $t->partner?->name }}</a>@endif</td><td>{{ $t->user?->name }}</td>
                <td class="actions"><form method="post" action="{{ route('corral.crm.tasks.complete', $t) }}" class="inline">@csrf<button class="btn sm ghost">Reopen</button></form></td></tr>
        @empty <tr><td colspan="5" class="empty">Nothing done yet.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
