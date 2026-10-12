@extends('backend.layouts.plain')

@section('title', $app->exists ? 'Edit '.$app->name : 'New Admin App')

@section('content')
    @include('backend.partials.page-head', [
        'title' => $app->exists ? 'Edit '.$app->name : 'New Admin App',
        'sub' => $app->exists ? 'Address: <span class="mono">/admin/'.e($app->key).'</span>' : 'It gets its own tile on the launcher and a sidebar with the links you add below.',
        'actions' => '<a class="btn ghost" href="'.($app->exists ? route('custom.show', $app->key) : route('admin.launcher')).'">Cancel</a>',
    ])

    <form method="post" action="{{ $app->exists ? route('apps.update', $app) : route('apps.store') }}" style="display:flex;flex-direction:column;gap:20px">
        @csrf @if ($app->exists) @method('put') @endif

        <div class="panel"><div class="panel-head"><h2>About the app</h2></div><div class="panel-body"><div class="form-grid">
            <label for="name">Name</label><input id="name" name="name" required maxlength="60" value="{{ old('name', $app->name) }}" placeholder="e.g. Marketing">
            @unless ($app->exists)
                <label for="key">Address</label>
                <div><input id="key" name="key" maxlength="40" class="mono" value="{{ old('key') }}" placeholder="made from the name, e.g. marketing">
                    <p class="help" style="margin-top:4px">The app opens at /admin/&lt;address&gt;. Lowercase letters, numbers and dashes. This can't be changed later.</p></div>
            @endunless
            <label for="description">Description</label><input id="description" name="description" required maxlength="200" value="{{ old('description', $app->description) }}" placeholder="Shown on the launcher tile">
            <label>Icon</label>
            <div class="icon-pick" role="radiogroup" aria-label="Icon">
                @foreach ($icons as $key => $label)
                    <label title="{{ $label }}"><input type="radio" name="icon" value="{{ $key }}" @checked(old('icon', $app->icon) === $key)><span class="ico">@include('backend.partials.icon', ['name' => $key])</span><small>{{ $label }}</small></label>
                @endforeach
            </div>
            <label>Who can open it</label>
            <div class="actions">
                @foreach ($roles as $role)
                    <label class="check"><input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, $roleIds) || $role->isAdministrator()) @disabled($role->isAdministrator())> {{ $role->name }}</label>
                @endforeach
                <span class="help">Administrator always has every app. You can change this later in Sheriff → Roles.</span>
            </div>
        </div></div></div>

        <div class="panel">
            <div class="panel-head"><h2>Sidebar links</h2><button type="button" class="btn sm ghost" data-clone="#menu-row" data-into="#menu-rows">+ Add Link</button></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Heading</th><th>Label</th><th>Opens</th><th>Or a URL</th><th></th></tr></thead>
                <tbody id="menu-rows">
                    @foreach ($rows as $i => $row)
                        @include('backend.apps._row', ['i' => $i, 'row' => $row])
                    @endforeach
                </tbody>
            </table></div>
            <p class="help" style="padding:0 18px 16px">Links with the same heading are grouped together. Pick an existing admin screen, or enter a URL (starting with https:// or /). Empty rows are ignored.</p>
        </div>

        <div class="actions"><button class="btn cyan">{{ $app->exists ? 'Save App' : 'Create App' }}</button></div>
    </form>

    @if ($app->exists)
        <form method="post" action="{{ route('apps.destroy', $app) }}" data-confirm="Delete {{ $app->name }}?|Its tile and sidebar are removed and roles lose access to it. The screens it links to are not affected.|Delete App">
            @csrf @method('delete')<button class="btn red">Delete App</button>
        </form>
    @endif

    <template id="menu-row">@include('backend.apps._row', ['i' => '__i__', 'row' => []])</template>
@endsection
