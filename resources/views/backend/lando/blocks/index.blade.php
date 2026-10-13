@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Content Blocks', 'sub' => 'HTML widgets. Add one to a page as a component (Edit Page → Page Components) or inside page content with its code. Modules take variables, so each page passes its own text.',
        'actions' => '<a class="btn ghost" href="'.route('lando.blocks.categories.create').'">New Category</a><a class="btn" href="'.route('lando.blocks.create', array_filter(['category' => $category])).'">Add a Content Block</a>'])

    <div class="actions" style="margin-bottom:14px">
        <a class="btn sm {{ $category === null ? '' : 'ghost' }}" href="{{ route('lando.blocks.index') }}">All Content Blocks</a>
        @foreach ($categories as $cat)<a class="btn sm {{ $category == $cat->id ? '' : 'ghost' }}" href="{{ route('lando.blocks.index', ['category' => $cat->id]) }}">{{ $cat->name }} <span class="help">{{ $cat->blocks_count }}</span></a>@endforeach
        <a class="btn sm {{ $category === '0' ? '' : 'ghost' }}" href="{{ route('lando.blocks.index', ['category' => 0]) }}">Uncategorized <span class="help">{{ $uncategorized }}</span></a>
    </div>
    <div>
        <div class="panel"><div class="table-wrap"><table class="table blocks-table">
            <thead><tr><th>Block</th><th>Category</th><th>Kind</th><th class="num">On Pages</th><th>Updated</th></tr></thead>
            <tbody>@forelse ($blocks as $b)
                @php
                    // Short name for the table: drop the category prefix and " - Primary"; the full name shows on hover and on the block's page
                    $short = trim(preg_replace(['/ - Primary$/', '/^'.preg_quote($b->category?->name ?? '', '/').' - /'], '', $b->name));
                    $vars = array_keys($b->variables());
                @endphp
                <tr class="click" data-href="{{ route('lando.blocks.edit', $b) }}">
                    <td><a href="{{ route('lando.blocks.edit', $b) }}" title="{{ $b->name }}"><b>{{ \Illuminate\Support\Str::limit($short, 34) }}</b></a>
                        @php
                            $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(preg_replace('/\[\[[^\]]*\]\]/', '', $b->html)))));
                            $uses = preg_match('/\[\[block\|([a-z0-9_-]+)/', $b->html, $um) ? $um[1] : null;
                        @endphp
                        <br><span class="help">{{ $text !== '' ? \Illuminate\Support\Str::limit($text, 48) : ($uses ? 'Uses module: '.$uses : 'Widgets only') }}</span></td>
                    <td>{{ $b->category?->name ?? 'Uncategorized' }}@if ($b->site)<br><span class="help">{{ $b->site->name }} only</span>@endif</td>
                    <td>@if ($vars)<span class="pill info" title="{{ implode(', ', $vars) }}">Module · {{ count($vars) }} {{ \Illuminate\Support\Str::plural('variable', count($vars)) }}</span>@else<span class="help">Fixed text</span>@endif</td>
                    <td class="num">{{ $b->components_count }}</td>
                    <td class="nowrap">{{ $b->updated_at->format('n/j/y') }}</td></tr>
            @empty <tr><td colspan="5" class="empty">No content blocks in this category.</td></tr> @endforelse</tbody>
        </table></div></div>
    </div>
@endsection
