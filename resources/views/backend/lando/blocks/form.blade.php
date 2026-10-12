@extends('backend.layouts.app')

@section('crumb', $block->exists ? 'Edit Content Block' : 'Add a Content Block')

@section('content')
    @include('backend.partials.page-head', ['title' => $block->exists ? 'Edit Content Block' : 'Add a Content Block', 'sub' => $block->exists ? '<span class="mono">'.e($block->shortcode()).'</span>' : null])
    <div class="grid-2">
        <form method="post" action="{{ $block->exists ? route('lando.blocks.update', $block) : route('lando.blocks.store') }}" class="panel"><div class="panel-body">
            @csrf @if ($block->exists) @method('put') @endif
            <div class="form-grid" style="grid-template-columns:1fr">
                <label for="name">Name</label><input id="name" name="name" required value="{{ old('name', $block->name) }}" data-slug-source>
                <label for="slug">Slug</label><input id="slug" name="slug" required class="mono" value="{{ old('slug', $block->slug) }}" @unless ($block->exists) data-slug-target @endunless>
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id"><option value="">Uncategorized</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $block->category_id) == $c->id)>{{ $c->name }}</option>@endforeach</select>
                <label for="site_id">Site</label>
                <select id="site_id" name="site_id"><option value="">All sites</option>@foreach ($sites as $s)<option value="{{ $s->id }}" @selected(old('site_id', $block->site_id) == $s->id)>{{ $s->name }} only</option>@endforeach</select>
                <label for="b-html">Content (HTML)</label><textarea id="b-html" name="html" spellcheck="false">{{ old('html', $block->html) }}</textarea>
            </div>
            <p class="help" style="margin-top:8px">Widgets you can use: <span class="mono">[[zip_form]] [[plans|group=featured|limit=3]] [[phone]] [[year]]</span> and other blocks with <span class="mono">[[block|slug]]</span>.</p>
            <div class="actions" style="margin-top:16px"><button class="btn cyan">Save Content Block</button><a class="btn ghost" href="{{ route('lando.blocks.index') }}">Cancel</a></div>
            <p class="help" style="margin-top:10px">Ctrl+S saves.</p>
        </div></form>
        <div style="display:flex;flex-direction:column;gap:20px">
            <div class="panel"><div class="panel-head"><h2>Preview</h2><span class="muted">widget codes shown as-is</span></div>
                <div class="panel-body"><iframe id="b-prev" title="Block preview" sandbox="" style="width:100%;min-height:300px;border:1px solid var(--line);border-radius:6px;background:#fff"></iframe></div>
            </div>
            @if ($block->exists)
                <div class="panel"><div class="panel-head"><h2>Used On</h2></div><div class="table-wrap"><table>
                    <thead><tr><th>Page</th><th>Site</th><th>Zone</th></tr></thead>
                    <tbody>@forelse ($usedOn as $u)
                        <tr><td><a href="{{ route('lando.pages.edit', $u->page) }}#components">{{ $u->page->title }}</a> <span class="mono help">{{ $u->page->url() }}</span></td><td>{{ $u->page->site?->name }}</td><td>{{ config('cms.zones.'.$u->zone) }}</td></tr>
                    @empty <tr><td colspan="3" class="empty">Not placed on any page as a component.</td></tr> @endforelse</tbody>
                </table></div></div>
                @can('delete')
                    <form method="post" action="{{ route('lando.blocks.destroy', $block) }}" data-confirm="Delete this content block?|It is also removed from every page it is on.|Delete Block">@csrf @method('delete')<button class="btn red">Delete Content Block</button></form>
                @endcan
            @endif
        </div>
    </div>
@endsection
