@extends('backend.layouts.app')

@section('crumb', $group->exists ? 'Edit Group' : 'Add a Group')

@section('content')
    @include('backend.partials.page-head', ['title' => $group->exists ? 'Edit Group: '.$group->name : 'Add a Group', 'sub' => 'Check the plans this group shows. Plans appear on the website in the order listed here.'])

    @php $chosen = collect(old('plans', $group->exists ? $group->plans->pluck('id')->all() : []))->map(fn ($id) => (int) $id); @endphp
    <form method="post" action="{{ $group->exists ? app_route('groups.update', $group) : app_route('groups.store') }}" class="panel"><div class="panel-body">
        @csrf @if ($group->exists) @method('put') @endif
        <div class="form-grid">
            <label for="name">Name</label><input id="name" name="name" required value="{{ old('name', $group->name) }}" data-slug-source>
            <label for="slug">Slug</label><input id="slug" name="slug" required class="mono" value="{{ old('slug', $group->slug) }}" @unless ($group->exists) data-slug-target @endunless>
        </div>
        <h3 style="margin-top:20px">Plans</h3>
        <ol class="sortable" data-sortable>
            {{-- Chosen plans first, in their current order, then the rest --}}
            @foreach ($chosen->map(fn ($id) => $plans->firstWhere('id', $id))->filter()->concat($plans->whereNotIn('id', $chosen)) as $p)
                <li><label class="check"><input type="checkbox" name="plans[]" value="{{ $p->id }}" @checked($chosen->contains($p->id))> {{ $p->name }} <span class="mono help">{{ $p->internal }}</span></label>
                    <span class="actions"><button type="button" class="btn sm ghost" data-move="-1" aria-label="Move {{ $p->name }} up">↑</button><button type="button" class="btn sm ghost" data-move="1" aria-label="Move {{ $p->name }} down">↓</button></span></li>
            @endforeach
        </ol>
        <div class="actions" style="margin-top:20px"><button class="btn cyan">Save Group</button><a class="btn ghost" href="{{ app_route('groups.index') }}">Cancel</a></div>
    </div></form>
@endsection
