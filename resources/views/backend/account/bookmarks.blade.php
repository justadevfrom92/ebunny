@extends('backend.layouts.app')

@section('crumb', 'My Bookmarks')

{{-- Customer accounts you bookmarked in Corral (the star on an account) --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'My Bookmarks', 'sub' => 'Customer accounts you bookmarked in Corral.'])

    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>Account #</th><th>Bookmarked</th><th>Customer</th><th>Status</th><th><span class="sr-only">Remove</span></th></tr></thead>
        <tbody>@forelse ($items as $c)
            <tr class="click" data-href="{{ route('corral.customers.show', $c) }}"><td class="nowrap"><a href="{{ route('corral.customers.show', $c) }}" class="mono"><b>{{ $c->account }}</b></a></td>
                <td class="nowrap">{{ $c->pivot->created_at?->format('n/j/Y') ?? '—' }}</td>
                <td><b>{{ $c->name }}</b><div class="help">{{ $c->type }}</div></td>
                <td>@include('backend.partials.pill', ['text' => $c->status, 'tone' => config('admin.customer_statuses')[$c->status] ?? ''])</td>
                <td class="actions"><form method="post" action="{{ route('admin.account.bookmarks.remove', $c) }}" data-confirm="Remove {{ $c->account }} from your bookmarks?|{{ $c->name }}|Remove">@csrf @method('delete')<button class="btn sm ghost">Remove</button></form></td></tr>
        @empty <tr><td colspan="5" class="empty">No bookmarks yet. Bookmark an account from its page in Corral.</td></tr> @endforelse</tbody>
    </table></div>
    @include('backend.partials.pager', ['p' => $items])
    </div>
@endsection
