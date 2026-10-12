@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Campaigns', 'sub' => 'Emails and texts to groups of customers. Only customers who opted in to marketing are included.',
        'actions' => '<a class="btn cyan" href="'.route('rodeo.campaigns.create').'">New Campaign</a>'])
    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>Campaign</th><th>Channel</th><th>Template</th><th>Status</th><th>Sent</th><th class="num">Recipients</th><th class="num">Opened</th><th class="num">Clicked</th></tr></thead>
        <tbody>@forelse ($campaigns as $c)
            <tr class="click" data-href="{{ route('rodeo.campaigns.edit', $c) }}"><td><a href="{{ route('rodeo.campaigns.edit', $c) }}"><b>{{ $c->name }}</b></a></td><td>{{ $c->channel }}</td><td>{{ $c->template?->name ?? '—' }}</td>
                <td>@include('backend.partials.pill', ['text' => ucfirst($c->status), 'tone' => $c->status === 'sent' ? 'ok' : 'info'])</td><td>{{ $c->sent_at?->format('n/j/Y') ?? '—' }}</td>
                <td class="num">{{ number_format($c->messages_count) }}</td><td class="num">{{ $c->channel === 'Email' && $c->messages_count ? round(100 * $c->opened_count / $c->messages_count).'%' : '—' }}</td>
                <td class="num">{{ $c->channel === 'Email' && $c->messages_count ? round(100 * $c->clicked_count / $c->messages_count).'%' : '—' }}</td></tr>
        @empty <tr><td colspan="8" class="empty">No campaigns yet.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
