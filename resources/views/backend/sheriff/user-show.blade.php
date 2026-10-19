@extends('backend.layouts.app')

@section('crumb', $u->name)

{{-- Staff directory profile: who they are, their team, what they can open and do in the admin, and Log in as (top right) --}}
@section('content')
    @php
        $loginBtn = $blocked
            ? '<span class="btn disabled" aria-disabled="true" data-tip="'.e($blocked).'">Log in as '.e(explode(' ', $u->name)[0]).'</span>'
            : '<form method="post" action="'.route('sheriff.users.login-as', $u).'" class="inline" data-confirm="Log in as '.e($u->name).'?|You\'ll see the admin exactly as they do. Everything you do is recorded under their name and yours. Use Back to return.|Log in as">'.csrf_field().'<button class="btn cyan">Log in as '.e(explode(' ', $u->name)[0]).'</button></form>';
    @endphp
    @include('backend.partials.page-head', ['title' => $u->name,
        'sub' => e($u->title ?? 'Employee').' · '.e($u->company ? $u->company.' (3rd party)' : ($u->department ?? 'No department')).($u->active ? '' : ' · <b>Sign-in disabled</b>'),
        'actions' => '<a class="btn ghost" href="'.route('sheriff.timeline', ['user' => $u->id]).'">Timeline</a><a class="btn ghost" href="'.route('sheriff.users.history', $u).'">History</a><a class="btn ghost" href="'.route('sheriff.users.edit', $u).'">Edit</a>'.$loginBtn])
    @error('login_as')<div class="alert bad" role="alert">{{ $message }}</div>@enderror

    <div class="grid-3">
        <div class="panel"><div class="panel-head"><h2>Profile</h2></div><div class="panel-body"><dl class="kv">
            <dt>Title</dt><dd>{{ $u->title ?? '—' }}</dd>
            @if ($u->company)
                <dt>Company</dt><dd>{{ $u->company }} <span class="pill info">3rd party</span></dd>
                <dt>Access Ends</dt><dd>{{ $u->access_ends_on?->format('n/j/Y') ?? 'No end date' }}@if ($u->access_ends_on?->isFuture()) <span class="muted">({{ $u->access_ends_on->diffForHumans() }})</span>@endif</dd>
            @else
                <dt>Department</dt><dd>{{ $u->department ?? '—' }}</dd>
                <dt>Employee #</dt><dd class="mono">{{ $u->employee_id ?? '—' }}</dd>
            @endif
            <dt>{{ $u->company ? 'Our Contact' : 'Manager' }}</dt><dd>@if ($u->manager)<a href="{{ route('sheriff.users.show', $u->manager) }}">{{ $u->manager->name }}</a>@else — @endif</dd>
            <dt>Started</dt><dd>{{ $u->hired_on?->format('n/j/Y') ?? '—' }}@if ($u->tenure()) <span class="muted">({{ $u->tenure() }})</span>@endif</dd>
            <dt>Location</dt><dd>{{ $u->location ?? '—' }}</dd>
        </dl></div></div>

        <div class="panel"><div class="panel-head"><h2>Contact &amp; Sign-in</h2></div><div class="panel-body"><dl class="kv">
            <dt>Email</dt><dd><a href="mailto:{{ $u->email }}">{{ $u->email }}</a></dd>
            <dt>Phone Ext.</dt><dd>{{ $u->phone_ext ?? '—' }}</dd>
            <dt>Status</dt><dd>@include('backend.partials.pill', $u->active ? ['text' => 'Can sign in', 'tone' => 'ok'] : ['text' => 'Disabled', 'tone' => 'bad'])</dd>
            <dt>Last Sign-in</dt><dd>{{ $u->last_login_at?->format('n/j/Y g:i A') ?? 'Never' }}@if ($u->last_login_at) <span class="muted">({{ $u->last_login_at->diffForHumans() }})</span>@endif</dd>
            <dt>Recent Sign-ins</dt><dd>@forelse ($logins as $l)<div class="help">{{ $l->created_at->format('n/j g:i A') }} · {{ $l->summary }}</div>@empty — @endforelse</dd>
        </dl></div></div>

        <div class="panel"><div class="panel-head"><h2>Team</h2><span class="muted">{{ $u->reports->count() }} {{ $u->company ? '' : 'direct' }} {{ Str::plural('report', $u->reports->count()) }}</span></div>
            @if ($u->reports->isNotEmpty())
                <div class="table-wrap"><table><tbody>@foreach ($u->reports as $r)<tr class="click" data-href="{{ route('sheriff.users.show', $r) }}"><td><a href="{{ route('sheriff.users.show', $r) }}"><b>{{ $r->name }}</b></a><div class="help">{{ $r->company ? $r->company.' · 3rd party' : $r->title }}</div></td></tr>@endforeach</tbody></table></div>
            @else
                <div class="panel-body muted">No one reports to {{ explode(' ', $u->name)[0] }}.@if ($u->manager) {{ explode(' ', $u->name)[0] }} reports to <a href="{{ route('sheriff.users.show', $u->manager) }}">{{ $u->manager->name }}</a>.@endif</div>
            @endif
        </div>
    </div>

    <div class="panel"><div class="panel-head"><h2>Admin Permissions</h2><span class="muted">role: <b>{{ $u->role?->name ?? 'none' }}</b> · <a href="{{ route('sheriff.users.edit', $u) }}">change</a></span></div><div class="table-wrap"><table>
        <thead><tr><th>App or Right</th><th>Access</th><th>Why</th></tr></thead>
        <tbody>
            @foreach ($apps as $key => $a)
                @php $has = $u->hasPerm($key); $ch = $changes[$key] ?? null; @endphp
                <tr><td><b>{{ $a['name'] }}</b></td>
                    <td>@include('backend.partials.pill', $has ? ['text' => 'Can open', 'tone' => 'ok'] : ['text' => 'No access', 'tone' => ''])</td>
                    <td class="help">{{ $ch === 'added' ? 'Added for this person' : ($ch === 'removed' ? 'Taken away for this person (their role has it)' : ($has ? 'From their role' : '—')) }}</td></tr>
            @endforeach
            @foreach ($rights as $key => $label)
                @php $has = $u->hasPerm($key); $ch = $changes[$key] ?? null; @endphp
                <tr><td><b>{{ $label }}</b></td>
                    <td>@include('backend.partials.pill', $has ? ['text' => 'Allowed', 'tone' => 'ok'] : ['text' => 'Not allowed', 'tone' => ''])</td>
                    <td class="help">{{ $ch === 'added' ? 'Added for this person' : ($ch === 'removed' ? 'Taken away for this person (their role has it)' : ($has ? 'From their role' : '—')) }}</td></tr>
            @endforeach
        </tbody>
    </table></div>
    @if ($u->access_note)<div class="panel-body help">Reason for the exceptions: {{ $u->access_note }}</div>@endif
    </div>

    <div class="panel"><div class="panel-head"><h2>Recent Activity</h2><a class="btn sm ghost" href="{{ route('sheriff.users.history', $u) }}">All History</a></div><div class="table-wrap"><table>
        <tbody>@forelse ($activity as $h)<tr class="click" data-href="{{ route('sheriff.history.show', $h) }}"><td class="nowrap" style="width:180px"><a href="{{ route('sheriff.history.show', $h) }}">{{ $h->created_at->format('n/j/Y g:i A') }}</a></td><td class="wrap">{{ $h->summary }}</td></tr>
        @empty <tr><td class="empty">No activity recorded.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
