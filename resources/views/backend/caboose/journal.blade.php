@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Journal', 'sub' => 'Daily double-entry journal of bills, payments, deposits, credits and refunds, for import into the accounting system.'])
    @unless ($quickbooks)<div class="banner-note">QuickBooks isn't connected (Sheriff → APIs → QuickBooks). Download the journal and import it.</div>@endunless
    <form method="get" class="panel"><div class="panel-body form-row">
        <div class="field"><label for="start">From</label><input id="start" name="start" type="date" value="{{ $start->toDateString() }}"></div>
        <div class="field"><label for="end">To</label><input id="end" name="end" type="date" value="{{ $end->toDateString() }}"></div>
        <button class="btn">Show</button>
        <button class="btn ghost" name="format" value="csv">Download CSV</button>
        <button class="btn ghost" name="format" value="xlsx">Download XLSX</button>
    </div></form>
    <div class="panel"><div class="panel-head"><h2>Entries</h2><span @class(['muted' => $debits === $credits])>Debits ${{ number_format($debits, 2) }} · Credits ${{ number_format($credits, 2) }}{{ $debits === $credits ? ' · balanced' : ' · NOT BALANCED' }}</span></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Date</th><th>Account</th><th class="num">Debit</th><th class="num">Credit</th><th>Memo</th></tr></thead>
            <tbody>@forelse ($lines as $l)<tr><td>{{ $l['date'] }}</td><td>{{ $l['account'] }}</td><td class="num">{{ $l['debit'] ? number_format($l['debit'], 2) : '' }}</td><td class="num">{{ $l['credit'] ? number_format($l['credit'], 2) : '' }}</td><td class="help">{{ $l['memo'] }}</td></tr>
            @empty <tr><td colspan="5" class="empty">No activity in this range.</td></tr> @endforelse</tbody>
        </table></div></div>
@endsection
