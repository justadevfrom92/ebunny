{{--
  Find a customer, then act on one: a search box (name, last name, phone, account, email, address),
  the matches in a table, and a button per customer. Pass: id, title, help, found (CustomerFinder::search),
  button (fn (Customer) => html) and optionally keep (other query values to carry along).
--}}
<div class="panel customer-finder" id="{{ $id }}">
    <div class="panel-head"><h2>{{ $title }}</h2></div>
    <div class="panel-body">
        <form method="get" action="{{ url()->current() }}#{{ $id }}" class="adv-bar" role="search">
            @foreach ($keep ?? [] as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <label class="search-field"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" name="{{ \App\Support\CustomerFinder::PARAM }}" value="{{ request(\App\Support\CustomerFinder::PARAM) }}" placeholder="Name, last name, phone, account number, email or address" aria-label="Find a customer"></label>
            <button class="btn cyan">Search</button>
        </form>
        @if ($help ?? null)<p class="help" style="margin-top:8px">{{ $help }}</p>@endif
    </div>
    @if ($found !== null)
        <div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Account</th><th>Name</th><th>Phone</th><th>Email</th><th>Status</th><th><span class="sr-only">Action</span></th></tr></thead>
            <tbody>@forelse ($found as $c)
                <tr><td class="nowrap"><a href="{{ route('corral.customers.show', $c) }}"><b>#{{ $c->id }}</b></a></td>
                    <td>@include('backend.partials.account-link', ['c' => $c])</td>
                    <td><b>{{ $c->name }}</b><div class="help">{{ $c->city }}</div></td>
                    <td class="nowrap">{{ $c->phone }}</td><td class="wrap">{{ $c->email }}</td>
                    <td>{{ $c->status }}</td>
                    <td class="nowrap" style="text-align:right">{!! $button($c) !!}</td></tr>
            @empty <tr><td colspan="7" class="empty">No customers match “{{ request(\App\Support\CustomerFinder::PARAM) }}”.</td></tr> @endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $found])
    @endif
</div>
