@extends('backend.layouts.app')

@section('crumb', 'Lead Approvals')

{{-- Changes on Friends & Family accounts waiting for a Lead. Leads see everyone's; others see their own requests. --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'Lead Approvals', 'sub' => $isLead ? 'Changes on Friends and Family accounts waiting for you.' : 'Your requests to a Lead.'])

    <div class="chips small" style="margin-bottom:14px">
        @foreach (['pending' => 'Waiting', 'approved' => 'Approved', 'denied' => 'Denied'] as $k => $l)
            <a href="{{ route('corral.approvals', ['status' => $k]) }}" @class(['chip-link', 'on' => $status === $k])>{{ $l }} ({{ $counts[$k] ?? 0 }})</a>
        @endforeach
    </div>

    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>ID#</th><th>Date</th><th>Account</th><th>Change</th><th>Asked By</th>@if ($status !== 'pending')<th>{{ ucfirst($status) }} By</th>@endif</tr></thead>
        <tbody>@forelse ($items as $a)
            <tr class="click" data-href="{{ route('corral.approvals.show', $a) }}"><td class="nowrap"><a href="{{ route('corral.approvals.show', $a) }}"><b>#{{ $a->id }}</b></a></td>
                <td class="nowrap">{{ $a->created_at->format('n/j/Y g:i A') }}</td>
                <td class="nowrap"><b>{{ $a->customer?->name }}</b><div class="help mono">{{ $a->customer?->account }}</div></td>
                <td class="wrap"><b>{{ $a->label() }}</b>@if ($a->details())<div class="help">{{ $a->details() }}</div>@endif</td>
                <td class="nowrap">{{ $a->requester?->name ?? '—' }}</td>
                @if ($status !== 'pending')<td class="nowrap">{{ $a->decider?->name ?? '—' }}</td>@endif</tr>
        @empty <tr><td colspan="6" class="empty">{{ $status === 'pending' ? 'Nothing waiting.' : 'None yet.' }}</td></tr> @endforelse</tbody>
    </table></div>
    @include('backend.partials.pager', ['p' => $items])
    </div>
@endsection
