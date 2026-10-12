{{-- Timeline of history entries. $route = which detail route to link to; $showAccount adds the account column. --}}
<div class="table-wrap"><table class="hist">
    <thead><tr><th>ID</th><th>Date</th><th>Model</th><th>Action</th><th>Summary</th>@if ($showAccount ?? false)<th>Account</th>@endif<th>By</th></tr></thead>
    <tbody>@forelse ($items as $i)
        <tr class="click" data-href="{{ route($route, $i) }}">
            <td class="mono"><a href="{{ route($route, $i) }}">{{ $i->id }}</a></td>
            <td style="white-space:nowrap">{{ $i->created_at->format('Y-m-d H:i:s') }}</td>
            <td class="mono">{{ $i->model }}</td>
            <td>@include('backend.partials.pill', ['text' => $i->action, 'tone' => ['created' => 'ok', 'updated' => 'info', 'deleted' => 'bad', 'sent' => 'info', 'received' => 'info'][$i->action] ?? ''])</td>
            <td class="wrap">{{ $i->summary }}</td>
            @if ($showAccount ?? false)<td class="mono">{{ $i->customer?->account ?? '—' }}</td>@endif
            <td>{{ $i->user?->name ?? 'System' }}</td>
        </tr>
    @empty
        <tr><td colspan="7" class="empty">No history matches.</td></tr>
    @endforelse</tbody>
</table></div>
