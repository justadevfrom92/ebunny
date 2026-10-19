@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Staff Directory', 'sub' => 'Everyone who signs in to the admin: employees by department, then 3rd-party company users by company. Open someone for their profile, permissions and Log in as.',
        'actions' => '<a class="btn cyan" href="'.route('sheriff.users.create').'">New User</a>'])

    {{-- Filters as you type (no button); ?q= still works for links --}}
    <form method="get" action="{{ route('sheriff.users.index') }}" class="search-box" role="search" onsubmit="return false">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input type="search" name="q" value="{{ $q }}" placeholder="Search name, title, department, company, email or employee #" aria-label="Search staff" data-live-filter autocomplete="off">
        <span class="search-count muted" data-live-count></span>
    </form>
    <div class="panel" data-live-empty hidden><div class="panel-body muted">Nobody matches that search.</div></div>

    <div class="stack">
        @foreach ($groups->map(fn ($l, $d) => [$d, $l, false])->values()->concat($external->map(fn ($l, $c) => [$c, $l, true])->values()) as [$department, $list, $ext])
            <div class="panel" data-filter-group><div class="panel-head"><h2>@if ($ext)<span class="pill info" style="margin-right:6px">3rd party</span>@endif{{ $department }}</h2><span class="muted">{{ $list->count() }} {{ Str::plural('person', $list->count()) }}</span></div>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>{{ $ext ? 'User' : 'Employee' }}</th><th>Role</th><th>Can Open</th><th>Rights</th><th>{{ $ext ? 'Contact / Access Ends' : 'Manager' }}</th><th>Last Sign-in</th></tr></thead>
                    <tbody>@foreach ($list as $u)
                        @php $changes = $u->accessChanges(); @endphp
                        <tr class="click" data-href="{{ route('sheriff.users.show', $u) }}" data-filter-row="{{ strtolower($u->name.' '.$u->title.' '.$u->email.' '.$u->department.' '.$u->company.' '.$u->employee_id.' '.$u->role?->name) }}">
                            <td><a href="{{ route('sheriff.users.show', $u) }}"><b>{{ $u->name }}</b></a>@if ($u->is(auth()->user())) <span class="muted">(you)</span>@endif
                                <div class="help">{{ $u->title ?? '—' }}@if ($u->employee_id) · <span class="mono">{{ $u->employee_id }}</span>@endif</div><div class="help">{{ $u->email }}@if ($u->phone_ext) · ext {{ $u->phone_ext }}@endif</div></td>
                            <td>{{ $u->role?->name ?? '—' }}</td>
                            <td class="wrap">@foreach ($apps as $key => $a)
                                    @if ($u->hasPerm($key))<span @class(['acc', 'added' => ($changes[$key] ?? null) === 'added']) title="{{ ($changes[$key] ?? null) === 'added' ? 'Added for this person' : 'From their role' }}">{{ $a['name'] }}</span>@elseif (($changes[$key] ?? null) === 'removed')<span class="acc removed" title="Their role has it; taken away for this person">{{ $a['name'] }}</span>@endif
                                @endforeach</td>
                            <td class="wrap">@foreach ($rights as $key => $label)
                                    @if ($u->hasPerm($key))<span @class(['acc', 'added' => ($changes[$key] ?? null) === 'added']) title="{{ $label }}">{{ ucfirst($key) }}</span>@elseif (($changes[$key] ?? null) === 'removed')<span class="acc removed" title="{{ $label }}: taken away for this person">{{ ucfirst($key) }}</span>@endif
                                @endforeach
                                @if ($u->access_note)<div class="help">{{ $u->access_note }}</div>@endif</td>
                            <td>{{ $u->manager?->name ?? '—' }}</td>
                            <td class="nowrap">{{ $u->last_login_at?->format('n/j/Y g:i A') ?? 'Never' }}</td>
                            </tr>
                    @endforeach</tbody>
                </table></div>
            </div>
        @endforeach

        @if ($disabled->isNotEmpty())
            <div class="panel" data-filter-group><div class="panel-head"><h2>Disabled</h2><span class="muted">can't sign in</span></div>
                <div class="table-wrap"><table class="table"><tbody>@foreach ($disabled as $u)
                    <tr class="click" data-href="{{ route('sheriff.users.show', $u) }}" data-filter-row="{{ strtolower($u->name.' '.$u->title.' '.$u->email.' '.$u->department.' '.$u->company.' '.$u->employee_id.' '.$u->role?->name) }}"><td><a href="{{ route('sheriff.users.show', $u) }}"><b>{{ $u->name }}</b></a><div class="help">{{ $u->title }} · {{ $u->email }}</div></td><td>{{ $u->department ?? '—' }}</td><td>{{ $u->role?->name }}</td>
                        <td class="actions"><form method="post" action="{{ route('sheriff.users.update', $u) }}" class="inline">@csrf @method('patch')<input type="hidden" name="active" value="1"><button class="btn sm ghost">Enable</button></form></td></tr>
                @endforeach</tbody></table></div>
            </div>
        @endif
    </div>
@endsection
