@extends('backend.layouts.app')

{{-- Bounty → Drawing Winners: every monthly drawing winner, newest first. Each row opens the winner's page. --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'Drawing Winners', 'sub' => 'Every monthly drawing winner. Winners are picked on Monthly Drawing.',
        'actions' => '<a class="btn ghost" href="'.route('bounty.drawing').'">Monthly Drawing</a>'])

    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Account</th><th>Winner</th><th>Month</th><th>Prize</th><th>Date Won</th><th><span class="sr-only">Remove</span></th></tr></thead>
        <tbody>@forelse ($rows as $r)
            @php [$month, $prize, $account, $drawn] = array_pad($r->cells, 4, null); $c = $customers[$account] ?? null; $u = route('bounty.winners.show', $r); @endphp
            <tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $r->id }}</b></a></td>
                <td>@if ($c)@include('backend.partials.account-link', ['c' => $c])@else<span class="mono">{{ $account }}</span>@endif</td>
                <td>{{ $c?->name ?? '—' }}</td>
                <td class="nowrap">{{ preg_match('/^\d{4}-\d{2}$/', (string) $month) ? \Illuminate\Support\Carbon::parse($month.'-01')->format('F Y') : $month }}</td>
                <td>{{ $prize }}</td>
                <td class="nowrap">{{ $drawn ? \Illuminate\Support\Carbon::parse($drawn)->format('n/j/Y') : '—' }}</td>
                <td class="actions"><form method="post" action="{{ route('bounty.winners.destroy', $r) }}" class="inline" data-confirm="Remove this winner?|The {{ $month }} winner (account {{ $account }}) is removed from Drawing Winners.|Remove">@csrf @method('delete')<button class="btn sm ghost">Remove</button></form></td></tr>
        @empty <tr><td colspan="7" class="empty">No winners yet.</td></tr> @endforelse</tbody>
    </table></div>@include('backend.partials.pager', ['p' => $rows])</div>
@endsection
