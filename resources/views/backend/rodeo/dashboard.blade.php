@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Marketing', 'sub' => 'Campaigns, customer surveys and where sign-ups come from.',
        'actions' => '<a class="btn cyan" href="'.route('rodeo.campaigns.create').'">New Campaign</a>'])
    <div class="grid-4 stat-row">
        <div class="stat"><span>Opted In to Marketing</span><strong>{{ number_format($optedIn) }}</strong><small>of {{ number_format($customers) }} customers</small></div>
        <div class="stat"><span>Campaigns Sent</span><strong>{{ $campaigns }}</strong><small>{{ number_format($messages) }} messages</small></div>
        <div class="stat"><span>Email Open / Click Rate</span><strong>{{ $openRate }}% / {{ $clickRate }}%</strong></div>
        <a class="stat" href="{{ route('rodeo.surveys') }}"><span>Survey Responses</span><strong>{{ number_format($responses) }}</strong></a>
    </div>
    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Sign-ups by MSID</h2><span class="muted">last 90 days</span></div><div class="table-wrap"><table>
            <thead><tr><th>MSID</th><th>Channel</th><th class="num">Orders</th></tr></thead>
            <tbody>@forelse ($byChannel as [$msid, $name, $n])<tr><td class="mono">{{ $msid }}</td><td>{{ $name }}</td><td class="num">{{ $n }}</td></tr>@empty<tr><td colspan="3" class="empty">No orders.</td></tr>@endforelse</tbody>
        </table></div></div>
        <div class="panel"><div class="panel-head"><h2>Recent Campaigns</h2><a class="btn sm ghost" href="{{ route('rodeo.campaigns') }}">All</a></div><div class="table-wrap"><table>
            <tbody>@forelse ($recent as $c)<tr class="click" data-href="{{ route('rodeo.campaigns.edit', $c) }}"><td><a href="{{ route('rodeo.campaigns.edit', $c) }}">{{ $c->name }}</a></td><td>{{ $c->channel }}</td><td>@include('backend.partials.pill', ['text' => ucfirst($c->status), 'tone' => $c->status === 'sent' ? 'ok' : 'info'])</td></tr>
            @empty <tr><td class="empty">No campaigns yet.</td></tr> @endforelse</tbody>
        </table></div></div>
    </div>
@endsection
