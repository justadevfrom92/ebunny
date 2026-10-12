@extends('backend.layouts.app')

@section('crumb', 'Account '.$c->account.' › API Log')

@section('content')
    @include('backend.partials.page-head', [
        'title' => 'API Log',
        'sub' => e($c->name).' · Account <span class="mono">'.e($c->account).'</span> · calls made to outside systems for this account. Request bodies and keys are never stored here.',
        'actions' => '<a class="btn ghost" href="'.route('corral.customers.show', $c).'#tech">Back to account</a>',
    ])

    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>Time</th><th>API</th><th>Action</th><th>Status</th><th class="num">Response</th></tr></thead>
        <tbody>@forelse ($logs as $l)
            <tr><td>{{ $l->created_at->format('n/j/Y g:i:s A') }}</td><td>{{ $l->api }}</td><td class="mono">{{ $l->action }}</td>
                <td>@include('backend.partials.pill', ['text' => $l->status, 'tone' => str_starts_with($l->status, '2') ? 'ok' : 'bad'])</td>
                <td class="num">{{ $l->response_ms !== null ? number_format($l->response_ms).' ms' : '—' }}</td></tr>
        @empty <tr><td colspan="5" class="empty">No API calls logged for this account.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
