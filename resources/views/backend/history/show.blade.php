@extends('backend.layouts.app')

@section('crumb', $item->model.' '.$item->id)

@php $route = request()->route()->getName(); @endphp

@section('content')
    @include('backend.partials.page-head', [
        'title' => $item->model,
        'sub' => e($item->summary).' — '.e($item->action).' '.$item->created_at->format('F jS, Y @ g:i:s A'),
        'actions' => $item->customer && auth()->user()->hasPerm('corral')
            ? (($recordUrl = $item->recordUrl()) ? '<a class="btn cyan" href="'.$recordUrl.'">'.(['ItemSms_model' => 'Open the text', 'ItemCall_model' => 'Open the call'][$item->model] ?? (str_starts_with($item->model, 'ItemEmail') ? 'Open the email' : 'Item page')).'</a>' : '')
              .($logKey ? '<a class="btn ghost" href="'.route('corral.customers.log', [$item->customer, $logKey]).'">Back to ticket</a>' : '')
              .'<a class="btn ghost" href="'.route('corral.customers.show', $item->customer).'">Back to account</a>'
            : ($item->user ? '<a class="btn ghost" href="'.route('sheriff.users.history', $item->user).'">'.e($item->user->name).'\'s history</a>' : ''),
    ])

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Data - item</h2><span class="mono muted">id {{ $item->id }}</span></div><div class="panel-body">
            <dl class="kv">
                <dt>model</dt><dd class="mono">{{ $item->model }}</dd>
                <dt>group</dt><dd>{{ $item->group }}</dd>
                <dt>record_id</dt><dd class="mono">{{ $item->record_id ?? '—' }}</dd>
                <dt>action</dt><dd>{{ $item->action }}</dd>
                <dt>created</dt><dd>{{ $item->created_at->format('Y-m-d H:i:s') }}</dd>
                <dt>by</dt><dd>{{ $item->actor() }}@if ($item->ip) <span class="muted">· {{ $item->ip }}</span>@endif</dd>
                @foreach ($item->data ?? [] as $field => $value)
                    <dt class="mono">{{ $field }}</dt><dd class="mono">{{ is_bool($value) ? ($value ? 'true' : 'false') : (string) $value }}</dd>
                @endforeach
            </dl>
        </div></div>

        <div style="display:flex;flex-direction:column;gap:20px">
            @if ($item->changes)
                <div class="panel"><div class="panel-head"><h2>Changes</h2></div><div class="table-wrap"><table>
                    <thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead>
                    <tbody>@foreach ($item->changes as $field => [$old, $new])
                        <tr><td class="mono">{{ $field }}</td><td class="wrap">{{ $old ?? '—' }}</td><td class="wrap"><b>{{ $new ?? '—' }}</b></td></tr>
                    @endforeach</tbody>
                </table></div></div>
            @endif

            <div class="panel"><div class="panel-head"><h2>Parent Tickets</h2></div><div class="panel-body">
                @if ($item->customer)
                    <dl class="kv">
                        {{-- Each parent links to its own ticket page --}}
                        @if ($logKey)<dt>Log ticket</dt><dd><a class="mono" href="{{ route('corral.customers.log', [$item->customer, $logKey]) }}">{{ \App\Http\Controllers\Backend\HistoryController::logTicketId($item->customer, $logKey) }}</a> · {{ $logTitle }}</dd>@endif
                        <dt>Account ticket</dt><dd><a class="mono" href="{{ route('corral.customers.ticket', $item->customer) }}">{{ $item->customer->ticket }}</a> · Account @include('backend.partials.account-link', ['c' => $item->customer]) · {{ $item->customer->name }}</dd></dl>
                @else
                    <p class="muted">Not tied to a customer account.</p>
                @endif
            </div></div>
        </div>
    </div>

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Child Tickets</h2></div><div class="panel-body muted">None.</div></div>
        <div class="panel"><div class="panel-head"><h2>Child Items</h2></div><div class="panel-body muted">None.</div></div>
    </div>

    <div class="panel"><div class="panel-head"><h2>Process Logs</h2><span class="muted">other {{ $item->model }} entries{{ $item->customer ? ' on this account' : ' for this record' }}</span></div>
        @include('backend.history._list', ['items' => $related, 'route' => $route])
    </div>
@endsection
