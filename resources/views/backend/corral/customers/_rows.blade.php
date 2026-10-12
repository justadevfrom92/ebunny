{{-- Customer result rows, shared by search results, bookmarks and the orders report --}}
<div class="table-wrap"><table>
    <thead><tr><th>Account</th><th>Created</th><th>Status</th><th>Customer</th><th>Contact</th><th>Address</th><th>Plan</th><th>Source</th></tr></thead>
    <tbody>
    @forelse ($customers as $c)
        <tr class="click" data-href="{{ route('corral.customers.show', $c) }}">
            <td class="mono"><a href="{{ route('corral.customers.show', $c) }}">{{ $c->account }}</a></td>
            <td>{{ $c->created_at->toDateString() }}</td>
            <td>@include('backend.partials.pill', ['text' => $c->status, 'tone' => $c->statusTone()]) @if ($c->exception) @include('backend.partials.pill', ['text' => $c->exception, 'tone' => 'bad']) @endif</td>
            <td><b>{{ $c->name }}</b><br><span class="muted">{{ $c->type }}</span></td>
            <td>{{ $c->phone }}<br><span class="muted">{{ $c->email }}</span></td>
            <td>{{ $c->address }}<br><span class="muted">{{ $c->city }}, TX {{ $c->zip }}</span></td>
            <td>{{ $c->plan?->name ?? '—' }}<br><span class="muted">{{ $c->market?->name }}</span></td>
            <td>{{ $c->source }}</td>
        </tr>
    @empty
        <tr><td colspan="8" class="empty">{{ $empty ?? 'Nothing to show.' }}</td></tr>
    @endforelse
    </tbody>
</table></div>
