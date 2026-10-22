@extends('backend.layouts.app')

@section('crumb', 'Add a Team Member')

{{-- Add someone to work tickets: a person already in the admin, or a new person (gets a sign-in with a temporary password). --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'Add a Team Member', 'sub' => 'They can open Ranger and work tickets. Pick someone already in the admin, or add a new person.'])

    <form method="post" action="{{ route('ranger.team.store') }}" class="panel" data-who-form>@csrf
        <div class="panel-body form-grid">
            <label>Who</label><div class="chips small">
                <label><input type="radio" name="who" value="existing" @checked(old('who', 'existing') === 'existing')><span>Someone already in the admin</span></label>
                <label><input type="radio" name="who" value="new" @checked(old('who') === 'new')><span>A new person</span></label></div>

            <label data-who="existing">Person</label>
            <div data-who="existing"><input type="search" class="search-box" placeholder="Find by name, company or role" data-live-filter aria-label="Find a person" style="margin-bottom:8px">
                <div class="table-wrap pick-list" style="max-height:260px"><table class="table"><tbody>
                    @forelse ($people as $p)<tr data-filter-row="{{ strtolower($p->name.' '.$p->company.' '.$p->role?->name.' '.$p->title) }}"><td style="width:32px"><input type="radio" id="p{{ $p->id }}" name="user_id" value="{{ $p->id }}" @checked(old('user_id') == $p->id)></td>
                        <td><label for="p{{ $p->id }}"><b>{{ $p->name }}</b></label> <span class="help">{{ $p->role?->name }}{{ $p->company ? ' · '.$p->company : '' }}</span></td></tr>
                    @empty <tr><td class="empty">Everyone in the admin is already on the team.</td></tr> @endforelse
                </tbody></table></div>
                @error('user_id')<div class="alert bad">{{ $message }}</div>@enderror</div>

            <label data-who="new" for="tm-name">Name</label><div data-who="new"><input id="tm-name" name="name" maxlength="150" value="{{ old('name') }}">@error('name')<div class="help" style="color:var(--red)">{{ $message }}</div>@enderror</div>
            <label data-who="new" for="tm-email">Email</label><div data-who="new"><input id="tm-email" name="email" type="email" maxlength="200" value="{{ old('email') }}">@error('email')<div class="help" style="color:var(--red)">{{ $message }}</div>@enderror</div>
            <label data-who="new" for="tm-pw">Temporary password</label><div data-who="new"><input id="tm-pw" name="password" type="password" minlength="12" autocomplete="new-password"><div class="help">At least 12 characters. Share it with them securely.</div>@error('password')<div class="help" style="color:var(--red)">{{ $message }}</div>@enderror</div>
            <label data-who="new" for="tm-title">Job title</label><input data-who="new" id="tm-title" name="title" maxlength="100" value="{{ old('title') }}" placeholder="Support Agent">

            @if ($company)
                <label>Company</label><div><b>{{ $company }}</b><input type="hidden" name="team" value="third_party"><div class="help">You add people to your own company's team (3rd Party).</div></div>
            @else
            <label>Team</label><div class="chips small">@foreach (\App\Models\RangerAgent::TEAMS as $k => $l)<label><input type="radio" name="team" value="{{ $k }}" @checked(old('team', 'office') === $k)><span>{{ $l }}</span></label>@endforeach</div>
            <label for="tm-company" data-team="third_party">Company</label><div data-team="third_party"><input id="tm-company" name="company" maxlength="120" value="{{ old('company') }}" placeholder="e.g. Brightline Contact Solutions"><div class="help">The 3rd-party company they work for.</div>@error('company')<div class="help" style="color:var(--red)">{{ $message }}</div>@enderror</div>
            @endif
            <label>Level</label><div><div class="chips small"><label><input type="radio" name="level" value="agent" @checked(old('level', 'agent') === 'agent')><span>Agent</span></label>
                @if ($manager)<label><input type="radio" name="level" value="manager" @checked(old('level') === 'manager')><span>Manager</span></label>@endif
                @if ($manager && ! $company)<label data-team-level="third_party"><input type="radio" name="level" value="company_admin" @checked(old('level') === 'company_admin')><span>Company Admin</span></label>@endif</div>
                <div class="help">Managers can take over a ticket, join in on one, and assign tickets to others.{{ $manager ? '' : ' Only a manager can add a manager.' }}@if ($manager && ! $company) A company admin (3rd party only) also runs their company's team: adds and changes their people and sees their numbers.@endif</div></div>
        </div>
        <div class="panel-body actions"><button class="btn cyan">Add to Team</button><a class="btn ghost" href="{{ route('ranger.team') }}">Cancel</a></div>
    </form>

    <script>
    (function () {
        var form = document.querySelector('[data-who-form]');
        function sync() {
            var who = form.querySelector('input[name=who]:checked').value, teamIn = form.querySelector('input[name=team]:checked') || form.querySelector('input[name=team]'), team = teamIn.value;
            form.querySelectorAll('[data-team-level]').forEach(function (el) { el.hidden = el.dataset.teamLevel !== team; if (el.hidden) el.querySelector('input').checked = false; });
            form.querySelectorAll('[data-who]').forEach(function (el) { el.hidden = el.dataset.who !== who; });
            form.querySelectorAll('[data-team]').forEach(function (el) { el.hidden = !(who === 'new' && el.dataset.team === team); });
        }
        form.addEventListener('change', sync); sync();
    })();
    </script>
@endsection
