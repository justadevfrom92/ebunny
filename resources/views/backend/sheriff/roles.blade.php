@extends('backend.layouts.app')

@section('content')
    <form method="post" action="{{ route('sheriff.roles.update') }}" style="display:flex;flex-direction:column;gap:20px">
        @csrf @method('put')
        @include('backend.partials.page-head', ['title' => 'Roles', 'sub' => 'Which apps and rights each role has. Administrator always has everything.', 'actions' => '<button class="btn cyan">Save Roles</button>'])
        <div class="panel"><div class="table-wrap"><table>
            <thead><tr><th>Role</th><th class="num">Users</th>@foreach ($perms as $label)<th style="text-align:center">{{ $label }}</th>@endforeach</tr></thead>
            <tbody>@foreach ($roles as $role)
                <tr><td><b>{{ $role->name }}</b></td><td class="num">{{ $role->users_count }}</td>
                    @foreach ($perms as $key => $label)
                        <td style="text-align:center"><input type="checkbox" name="perms[{{ $role->id }}][]" value="{{ $key }}" @checked(in_array($key, $role->perms)) @disabled($role->isAdministrator()) aria-label="{{ $role->name }}: {{ $label }}"></td>
                    @endforeach</tr>
            @endforeach</tbody>
        </table></div></div>
    </form>
@endsection
