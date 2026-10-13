@extends('backend.layouts.app')

@section('crumb', $page->exists ? 'Edit Page' : 'Add a Page')

{{-- Laid out like the original Lando "Edit Page": Basic, Meta Data, Content, Page Components, Edits. --}}
@section('content')
    @include('backend.partials.page-head', [
        'title' => $page->exists ? 'Edit Page: '.$page->title : 'Add a Page',
        'sub' => e($page->site?->name).($page->exists ? ' · <span class="mono">'.e($page->url()).'</span>' : ''),
        'actions' => $page->exists && ! $page->redirect ? '<a class="btn ghost" href="'.e($page->liveUrl()).'" target="_blank" rel="noopener">View Page Live</a>' : null,
    ])

    @if ($page->exists)
        <nav class="jump" aria-label="Sections">@foreach (['basic' => 'Basic', 'meta' => 'Meta Data', 'content' => 'Content', 'pricegrid' => 'Price Grid', 'components' => 'Page Components', 'edits' => 'Edits'] as $id => $l)<a href="#{{ $id }}">{{ $l }}</a>@endforeach</nav>
    @endif
    @if ($page->file)
        <div class="alert info"><b>Built-in page.</b> This page's layout is the file <span class="mono">public/{{ $page->file }}</span>. Its title, meta data and status are used here; content areas don't apply. Add components to its top and bottom zones below.</div>
    @endif

    <form method="post" action="{{ $page->exists ? app_route('pages.update', $page) : app_route('pages.store') }}" style="display:flex;flex-direction:column;gap:20px">
        @csrf @if ($page->exists) @method('put') @endif

        <div class="panel" id="basic"><div class="panel-head"><h2>Basic</h2><button class="btn cyan">Save Page</button></div><div class="panel-body form-grid">
            @unless ($page->exists)
                <label for="site_id">Site</label>
                <select id="site_id" name="site_id">@foreach ($sites as $s)<option value="{{ $s->id }}" @selected(old('site_id', $page->site_id) == $s->id)>{{ $s->name }} ({{ $s->domain }})</option>@endforeach</select>
            @endunless
            <label for="title">Title</label><input id="title" name="title" required value="{{ old('title', $page->title) }}">
            <label for="path">Page Slug / Path</label><input id="path" name="path" required class="mono" value="{{ old('path', $page->path) }}" placeholder="get-to-learnin/new-article" @readonly($page->file)>
            <label for="template_id">Template</label>
            <select id="template_id" name="template_id"><option value="">—</option>@foreach ($templates as $t)<option value="{{ $t->id }}" @selected(old('template_id', $page->template_id) == $t->id)>{{ $t->name }}</option>@endforeach</select>
            <label for="status">Status</label>
            <select id="status" name="status">@foreach (['Published', 'Draft'] as $s)<option @selected(old('status', $page->status) === $s)>{{ $s }}</option>@endforeach</select>
            <label for="redirect">Redirect To</label><input id="redirect" name="redirect" value="{{ old('redirect', $page->redirect) }}" placeholder="Leave blank for none, e.g. /plans.html">
            <label for="promo_code">Promo Code</label><input id="promo_code" name="promo_code" class="mono" value="{{ old('promo_code', $page->promo_code) }}" placeholder="Applied to orders that start on this page">
            <label for="market_label">Market Label</label><input id="market_label" name="market_label" value="{{ old('market_label', $page->market_label) }}" placeholder="e.g. Houston — shown above the page title">
            <label for="rep_id">REP ID</label>
            <select id="rep_id" name="rep_id"><option value="">None</option>@foreach (config('cms.reps') as $id => $rep)<option value="{{ $id }}" @selected(old('rep_id', $page->rep_id) == $id)>{{ $rep }}</option>@endforeach</select>
            @unless ($page->file)
                <label for="copy_from_id">Copy From Page ID</label>
                <div style="display:flex;flex-direction:column;gap:6px">
                    <input id="copy_from_id" name="copy_from_id" inputmode="numeric" value="{{ old('copy_from_id', $page->copy_from_id) }}" placeholder="Page ID (shown in the page list)" style="max-width:220px">
                    <label class="check"><input type="radio" name="copy_mode" value="once" @checked(old('copy_mode', $page->copy_from_id ? 'sync' : 'once') === 'once')> Copy contents one time</label>
                    <label class="check"><input type="radio" name="copy_mode" value="sync" @checked(old('copy_mode', $page->copy_from_id ? 'sync' : 'once') === 'sync')> Keep contents in sync</label>
                    @if ($page->copyFrom)<span class="help">Showing the content of <a href="{{ app_route('pages.edit', $page->copyFrom) }}">#{{ $page->copyFrom->id }} {{ $page->copyFrom->title }}</a>. Clear the ID to edit this page's own content.</span>@endif
                </div>
            @endunless
            @if ($page->exists)<label>Created / Modified</label><span class="muted">{{ $page->created_at?->format('Y-m-d H:i:s') }} · {{ $page->updated_at?->format('Y-m-d H:i:s') }}</span>@endif
        </div></div>

        <div class="panel" id="meta"><div class="panel-head"><h2>Details, Meta Data</h2></div><div class="panel-body form-grid">
            <label for="html_title">HTML Title</label><input id="html_title" name="html_title" value="{{ old('html_title', $page->html_title) }}" placeholder="Browser tab / search result title; blank uses Title">
            <label for="meta_description">Meta Description</label><input id="meta_description" name="meta_description" value="{{ old('meta_description', $page->meta_description) }}">
            <label for="meta_keywords">Meta Keywords</label><input id="meta_keywords" name="meta_keywords" value="{{ old('meta_keywords', $page->meta_keywords) }}">
            <label for="head_content">Head Content</label><textarea id="head_content" name="head_content" style="min-height:90px" placeholder="Extra meta tags, etc.">{{ old('head_content', $page->head_content) }}</textarea>
            <label for="address_box_title">Address Box Title</label><input id="address_box_title" name="address_box_title" value="{{ old('address_box_title', $page->address_box_title) }}" placeholder="Leave blank to use the site default">
            <label for="phone">Display Phone Number</label><input id="phone" name="phone" value="{{ old('phone', $page->phone) }}" placeholder="Leave blank to use the site's">
            <label>Options</label>
            <div style="display:flex;flex-direction:column;gap:6px">
                <label class="check"><input type="hidden" name="canonical" value="0"><input type="checkbox" name="canonical" value="1" @checked(old('canonical', $page->canonical))> Canonical tag</label>
                <label class="check"><input type="hidden" name="no_index" value="0"><input type="checkbox" name="no_index" value="1" @checked(old('no_index', $page->no_index))> Disable SEO index (also leaves it out of the sitemap)</label>
                <label class="check"><input type="hidden" name="hard_cache" value="0"><input type="checkbox" name="hard_cache" value="1" @checked(old('hard_cache', $page->hard_cache))> Hard cache (serve the saved HTML; refreshes when the page or a content block is saved, or hourly)</label>
            </div>
        </div></div>

        @unless ($page->file)
            <div class="panel" id="content"><div class="panel-head"><h2>Content</h2><span class="muted">Pick predefined content for each area. Content is written in Lando → Content Blocks.</span></div>
                <div class="panel-body form-grid" style="grid-template-columns:1fr">
                    <label for="page_title">Page Title (the banner heading; blank uses Title)</label><input id="page_title" name="page_title" value="{{ old('page_title', $page->contentSource()->page_title) }}" @disabled($page->copyFrom)>
                    @foreach (\App\Models\Page::AREAS as $area => $areaLabel)
                        @php $f = 'content_'.$area.'_id'; $chosen = old($f, $page->contentSource()->$f); @endphp
                        <label for="{{ $f }}">{{ $areaLabel }}@if ($area === 'parent') (shown under the banner heading)@elseif ($area === 'amp') (served at /amp/{{ ltrim($page->path ?? 'path', '/') }})@endif</label>
                        <div class="form-row">
                            <select id="{{ $f }}" name="{{ $f }}" @disabled($page->copyFrom) style="flex:1">
                                <option value="">-- None --</option>
                                @foreach ($blocks->groupBy(fn ($b) => $b->category?->name ?? 'Uncategorized') as $cat => $list)
                                    <optgroup label="{{ $cat }}">@foreach ($list as $b)<option value="{{ $b->id }}" @selected($chosen == $b->id)>{{ $b->name }}</option>@endforeach</optgroup>
                                @endforeach
                            </select>
                            @if ($chosen && ($editUrl = app_route('blocks.edit', $chosen)))<a class="btn sm ghost" href="{{ $editUrl }}">Edit content</a>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endunless

        <div class="panel" id="pricegrid"><div class="panel-head"><h2>Price Grid Settings</h2><span class="muted">A list of plans with prices, shown after the page content</span></div><div class="panel-body form-grid">
            <label for="pricegrid_type">Price Grid Type</label>
            <select id="pricegrid_type" name="pricegrid_type"><option value="">No price grid</option>@foreach (config('cms.price_grids') as $k => $l)<option value="{{ $k }}" @selected(old('pricegrid_type', $page->pricegrid_type) === $k)>{{ $l }}</option>@endforeach</select>
            <label for="pricegrid_header">Price Grid Header</label><input id="pricegrid_header" name="pricegrid_header" value="{{ old('pricegrid_header', $page->pricegrid_header) }}" placeholder="Blank: “Plans for (market label)”">
            <h3 style="grid-column:1/-1;margin:8px 0 0">Markets and Bundles</h3>
            <label for="pricegrid_group_id">Plans (Plan Group)</label>
            <select id="pricegrid_group_id" name="pricegrid_group_id"><option value="">--Select--</option>@foreach ($groups as $g)<option value="{{ $g->id }}" @selected(old('pricegrid_group_id', $page->pricegrid_group_id) == $g->id)>{{ $g->name }} ({{ $g->slug }})</option>@endforeach</select>
            <label for="rating_formula">Rating Formula</label>
            <select id="rating_formula" name="rating_formula"><option value="">--Select--</option>@foreach (config('cms.rating_formulas') as $k => $l)<option value="{{ $k }}" @selected(old('rating_formula', $page->rating_formula) === $k)>{{ $l }}</option>@endforeach</select>
            <label for="market_id">Default Market</label>
            <select id="market_id" name="market_id"><option value="">--None--</option>@foreach ($markets as $m)<option value="{{ $m->id }}" @selected(old('market_id', $page->market_id) == $m->id)>{{ $m->name }}</option>@endforeach</select>
        </div></div>

        <div class="actions"><button class="btn cyan">Save Page</button><a class="btn ghost" href="{{ app_route('pages.index', ['site' => $page->site_id]) }}">Cancel</a></div>
    </form>

    @if ($page->exists)
        <div class="panel" id="components"><div class="panel-head"><h2>Page Components</h2><span class="muted">Content blocks shown on this page, by zone</span></div>
            <div class="panel-body">
                @foreach ($zones as $zone => $zoneLabel)
                    <h3>{{ $zoneLabel }}</h3>
                    @forelse ($components[$zone] ?? [] as $c)
                        <div class="row-line"><span><b>{{ $c->block->name }}</b> <span class="mono help">{{ $c->block->slug }}</span>@if ($c->block->category) <span class="help">· {{ $c->block->category->name }}</span>@endif</span>
                            <span class="actions">
                                @foreach (['up' => '↑', 'down' => '↓'] as $dir => $arrow)
                                    <form method="post" action="{{ app_route('pages.components.move', $c) }}" class="inline">@csrf<input type="hidden" name="dir" value="{{ $dir }}"><button class="btn sm ghost" aria-label="Move {{ $c->block->name }} {{ $dir }}">{{ $arrow }}</button></form>
                                @endforeach
                                @if ($blockUrl = app_route('blocks.edit', $c->block))<a class="btn sm ghost" href="{{ $blockUrl }}">Edit Block</a>@endif
                                <form method="post" action="{{ app_route('pages.components.destroy', $c) }}" class="inline">@csrf @method('delete')<button class="link-btn">[ remove ]</button></form>
                            </span></div>
                        @if ($vars = $c->block->variables())
                            {{-- A module: this page's own text for each of the block's variables --}}
                            <form method="post" action="{{ app_route('pages.components.update', $c) }}" class="comp-vars">@csrf @method('patch')
                                @foreach ($vars as $name => $default)
                                    <div class="field"><label for="v{{ $c->id }}-{{ $name }}">{{ \Illuminate\Support\Str::headline($name) }}</label>
                                        <input id="v{{ $c->id }}-{{ $name }}" name="vars[{{ $name }}]" value="{{ $c->vars[$name] ?? '' }}" placeholder="{{ $default !== '' ? $default : 'Text for '.$name }}"></div>
                                @endforeach
                                <button class="btn sm">Save Text</button>
                            </form>
                        @endif
                    @empty <p class="help" style="margin-bottom:10px">Nothing here.</p> @endforelse
                @endforeach
                <form method="post" action="{{ app_route('pages.components.store', $page) }}" class="form-row" style="margin-top:16px">@csrf
                    <select name="content_block_id" required aria-label="Content block" style="flex:2"><option value="">-- Add a content block --</option>
                        @foreach ($blocks->groupBy(fn ($b) => $b->category?->name ?? 'Uncategorized') as $cat => $list)
                            <optgroup label="{{ $cat }}">@foreach ($list as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</optgroup>
                        @endforeach
                    </select>
                    <select name="zone" aria-label="Zone">@foreach ($zones as $zone => $zoneLabel)<option value="{{ $zone }}">{{ $zoneLabel }}</option>@endforeach</select>
                    <button class="btn sm">Add Component</button>
                </form>
            </div>
        </div>

        <div class="panel" id="edits"><div class="panel-head"><h2>Edits</h2></div>
            <div class="table-wrap"><table><thead><tr><th>Date</th><th>User</th><th>Change</th></tr></thead><tbody>
                @forelse ($edits as $e)
                    <tr><td>{{ $e->created_at->format('n/j/Y g:i A') }}</td><td>{{ $e->user?->name ?? 'System' }}</td><td>{{ $e->summary }}</td></tr>
                @empty <tr><td colspan="3" class="empty">No edits recorded.</td></tr> @endforelse
            </tbody></table></div></div>

        @can('delete')
            @unless ($page->file)
                <form method="post" action="{{ app_route('pages.destroy', $page) }}" data-confirm="Delete page?|This removes {{ $page->url() }} from {{ $page->site?->name }}.|Delete This Page">
                    @csrf @method('delete')<button class="btn red">Delete This Page</button>
                </form>
            @endunless
        @endcan
    @endif
@endsection
