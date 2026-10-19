{{-- Top right: the number of finished files you haven't looked at, then your name. Clicking your name opens My Files, Switch user and Logout. --}}
@php
    $unseen = \App\Models\ReportRun::unseenFor($user->id)->count();
    $myFiles = \App\Models\ReportRun::where('user_id', $user->id)->count();
    $testUsers = \App\Http\Controllers\Backend\AuthController::testUsers()->reject(fn ($u) => $u->is($user));
@endphp
<div class="user-menu" data-user-menu data-notify="{{ route('admin.notifications') }}">
    <a class="files-badge" href="{{ route('admin.files') }}" data-files-badge @if (! $unseen) hidden @endif aria-label="{{ $unseen }} new {{ Str::plural('file', $unseen) }}" data-tip="New files to see">{{ $unseen }}</a>
    <button type="button" class="user-chip" aria-haspopup="true" aria-expanded="false" data-user-toggle>
        <span class="avatar">{{ $user->initials() }}</span><span><span>{{ $user->name }}</span><small>{{ $user->role?->name }}</small></span>
        <svg class="caret" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </button>
    <div class="user-pop" data-user-pop hidden>
        <div class="up-head"><b>{{ $user->name }}</b><span>{{ $user->email }}</span></div>
        <a class="up-item" href="{{ route('admin.files') }}"><span>My Files</span><span class="up-count" data-files-total>{{ $myFiles }}</span>@if ($unseen)<span class="up-new" data-files-new>{{ $unseen }} new</span>@endif</a>
        @if ($testUsers->isNotEmpty())
            <div class="up-label">Switch user</div>
            <input type="search" class="up-search" placeholder="Find a user" aria-label="Find a user to switch to" data-up-search>
            <form method="post" class="up-users" data-test-switch>@csrf
                @foreach ($testUsers as $u)<button type="submit" formaction="{{ route('admin.login.test', $u) }}" data-name="{{ strtolower($u->name.' '.$u->role?->name) }}"><span class="avatar sm">{{ $u->initials() }}</span><span><b>{{ $u->name }}</b><small>{{ $u->role?->name }}</small></span></button>@endforeach
            </form>
        @endif
        <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="up-item up-logout">Logout</button></form>
    </div>
</div>
