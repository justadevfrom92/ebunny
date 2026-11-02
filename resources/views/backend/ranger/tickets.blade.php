@extends('backend.layouts.app')

@section('content')
    @php
        $views = ['active' => 'Open', 'mine' => 'Mine', 'unassigned' => 'Unassigned'] + ($manager ? ['manager' => 'Manager Requests'] : []) + ['watching' => 'Joined', 'done' => 'Solved & Closed', 'all' => 'All'];
        $url = fn (array $set) => route('ranger.tickets', array_filter(array_merge($f, ['view' => $view], $set), fn ($v) => $v !== null && $v !== ''));
    @endphp
    @include('backend.partials.page-head', ['title' => $customer ? $customer->name.' — Ticket History' : ($owner ? $owner->name.'\'s Tickets' : ($view === 'unassigned' ? 'Unassigned Tickets' : 'Tickets')),
        'sub' => filled($f['q'] ?? null) ? 'Tickets matching “'.e($f['q']).'”.' : ($customer ? 'Every ticket account '.$customer->account.' has opened, newest first.' : ($view === 'unassigned' ? 'Nobody has these yet. Urgent and high first, then the longest waiting. Click one to pick it up.' : 'Urgent and high first, then the longest waiting. Click a ticket to open it.')),
        'actions' => ($owner || $customer) ? '<a class="btn ghost" href="'.route('ranger.tickets', ['view' => $view]).'">Everyone\'s Tickets</a>' : null])

    {{-- Search: ticket number, words in the subject and messages, notes, the customer (name, email, phone, account) and the owner --}}
    @php
        $adv = collect(['in', 'status', 'priority', 'topic', 'channel', 'from', 'to', 'handled_by', 'owner'])->contains(fn ($k) => filled($f[$k] ?? null));
        $searching = $adv || filled($f['q'] ?? null);
        $in = $f['in'] ?? [];
    @endphp
    <form method="get" action="{{ route('ranger.tickets') }}" class="panel adv-search" role="search">
        <div class="panel-body">
            @if (isset($f['view']))<input type="hidden" name="view" value="{{ $view }}">@endif
            @if (isset($f['customer']))<input type="hidden" name="customer" value="{{ $f['customer'] }}">@endif
            <div class="adv-bar">
                <label class="search-field"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="search" name="q" value="{{ $f['q'] ?? '' }}" placeholder="Search tickets: words, notes, names, phone, email, account or ticket number" aria-label="Search tickets"></label>
                <button class="btn cyan">Search</button>
                @if ($searching)<a class="btn ghost" href="{{ route('ranger.tickets', array_filter(['view' => $f['view'] ?? null, 'customer' => $f['customer'] ?? null])) }}">Clear</a>@endif
            </div>
            <details class="adv-more" @if ($adv) open @endif>
                <summary>Advanced search</summary>
                <div class="adv-fields">
                    <div class="field"><span class="label">Look in</span>
                        <div class="adv-checks">
                            @foreach (['text' => 'Subject & messages', 'notes' => 'Notes', 'people' => 'Customer & owner (names, email, phone, account)'] as $k => $l)
                                <label><input type="checkbox" name="in[]" value="{{ $k }}" @checked(in_array($k, $in, true))> {{ $l }}</label>
                            @endforeach
                        </div><span class="help">None ticked looks everywhere.</span></div>
                    <div class="field"><label for="adv-status">Status</label><select id="adv-status" name="status"><option value="">Any</option>@foreach (\App\Models\SupportTicket::STATUSES as $k => [$l])<option value="{{ $k }}" @selected(($f['status'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="field"><label for="adv-priority">Priority</label><select id="adv-priority" name="priority"><option value="">Any</option>@foreach (\App\Models\SupportTicket::PRIORITIES as $k => [$l])<option value="{{ $k }}" @selected(($f['priority'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="field"><label for="adv-topic">Topic</label><select id="adv-topic" name="topic"><option value="">Any</option>@foreach (\App\Models\SupportTicket::TOPICS as $k => $l)<option value="{{ $k }}" @selected(($f['topic'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="field"><label for="adv-channel">Came in by</label><select id="adv-channel" name="channel"><option value="">Any</option>@foreach (array_keys(\App\Models\SupportTicket::CHANNELS) as $k)<option @selected(($f['channel'] ?? '') === $k)>{{ $k }}</option>@endforeach</select></div>
                    <div class="field"><label for="adv-owner">Owner</label><select id="adv-owner" name="owner"><option value="">Anyone</option>@foreach ($people as $p)<option value="{{ $p->id }}" @selected((int) ($f['owner'] ?? 0) === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
                    <div class="field"><label for="adv-handled">Handled by</label><select id="adv-handled" name="handled_by"><option value="">Anyone</option>@foreach ($people as $p)<option value="{{ $p->id }}" @selected((int) ($f['handled_by'] ?? 0) === $p->id)>{{ $p->name }}</option>@endforeach</select><span class="help">Owned, replied to or wrote a note on it.</span></div>
                    <div class="field"><label for="adv-from">Opened from</label><input id="adv-from" type="date" name="from" value="{{ $f['from'] ?? '' }}"></div>
                    <div class="field"><label for="adv-to">Opened to</label><input id="adv-to" type="date" name="to" value="{{ $f['to'] ?? '' }}"></div>
                    <div><button class="btn cyan">Search</button></div>
                </div>
            </details>
        </div>
    </form>

    <div class="chips small" style="margin-bottom:12px">@foreach ($views as $k => $l)<a class="chip-link {{ $view === $k ? 'on' : '' }}" href="{{ $url(['view' => $k, 'page' => null]) }}">{{ $l }} · {{ $counts[$k] }}</a>@endforeach</div>


    <div class="panel"><div class="panel-head"><h2>{{ number_format($tickets->total()) }} {{ Str::plural('ticket', $tickets->total()) }}</h2></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Ticket</th><th>Subject</th><th>Customer</th><th>Status</th><th>Owner</th><th>Rating</th></tr></thead>
            <tbody>@forelse ($tickets as $t)@include('backend.ranger._row')@empty<tr><td colspan="6" class="empty">No tickets here.</td></tr>@endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $tickets])
    </div>
@endsection
