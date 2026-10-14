@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Partners & Brokers', 'sub' => 'Brokers, referral partners, property managers and builders who send us leads, what they\'ve sent and what they\'ve earned.',
        'actions' => '<a class="btn cyan" href="'.route('corral.crm.partners.create').'">New Partner</a>'])
    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>Partner</th><th>Kind</th><th>Contact</th><th class="num">Leads</th><th class="num">Open</th><th class="num">Won</th><th class="num">Commission Earned</th><th>Commission</th><th>Status</th></tr></thead>
        <tbody>@foreach ($partners as $p)<tr class="click" data-href="{{ route('corral.crm.partners.show', $p) }}">
            <td><a href="{{ route('corral.crm.partners.show', $p) }}"><b>{{ $p->name }}</b></a><div class="help">{{ $p->city }}</div></td><td>{{ $p->kind }}</td>
            <td>{{ $p->contact_name }}<div class="help">{{ $p->phone }}</div></td>
            <td class="num">{{ $p->leads_count }}</td><td class="num">{{ $p->open_count }}</td><td class="num">{{ $p->won_count }}</td>
            <td class="num">${{ number_format($earned[$p->id] ?? 0, 2) }}</td><td class="help">{{ $p->commissionLabel() }}</td>
            <td>@include('backend.partials.pill', ['text' => $p->status, 'tone' => ['Active' => 'ok', 'Paused' => 'warn'][$p->status] ?? ''])</td></tr>@endforeach</tbody>
    </table></div></div>
@endsection
