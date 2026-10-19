{{-- Exceptions people fixed, newest first, 25 to a page. $history, $showQueue --}}
<div class="panel" id="history"><div class="panel-head"><h2>History</h2><span class="muted">{{ number_format($history->total()) }} fixed</span></div>
    <div class="table-wrap"><table>
        <thead><tr><th>Fixed</th>@if ($showQueue)<th>Queue</th>@endif<th>Account</th><th>Item</th><th>Opened</th><th>Took</th><th>Fixed By</th></tr></thead>
        <tbody>@forelse ($history as $item)
            <tr><td class="nowrap">{{ $item->resolved_at->format('n/j/Y g:i A') }}</td>
                @if ($showQueue)<td>{{ config('admin.queues.'.$item->queue)[0] ?? $item->queue }}</td>@endif
                <td>@include('backend.partials.account-link', ['c' => $item->customer])<br><span class="help">{{ $item->customer?->name }}</span></td>
                <td class="wrap">{{ $item->summary }}</td>
                <td class="nowrap">{{ $item->created_at->format('n/j/Y') }}</td>
                <td class="nowrap">{{ $item->created_at->diffForHumans($item->resolved_at, true) }}</td>
                <td>{{ $item->resolver?->name ?? 'System' }}</td></tr>
        @empty <tr><td colspan="{{ $showQueue ? 7 : 6 }}" class="empty">Nothing fixed yet.</td></tr> @endforelse</tbody>
    </table></div>
    @include('backend.partials.pager', ['p' => $history])
</div>
