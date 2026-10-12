@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Content Blocks', 'sub' => 'HTML widgets. Add one to a page as a component (Edit Page → Page Components) or inside page content with its code.',
        'actions' => '<a class="btn ghost" href="'.route('lando.blocks.categories.create').'">New Category</a><a class="btn" href="'.route('lando.blocks.create', array_filter(['category' => $category])).'">Add a Content Block</a>'])

    <div class="actions" style="margin-bottom:14px">
        <a class="btn sm {{ $category === null ? '' : 'ghost' }}" href="{{ route('lando.blocks.index') }}">All Content Blocks</a>
        @foreach ($categories as $cat)<a class="btn sm {{ $category == $cat->id ? '' : 'ghost' }}" href="{{ route('lando.blocks.index', ['category' => $cat->id]) }}">{{ $cat->name }} <span class="help">{{ $cat->blocks_count }}</span></a>@endforeach
        <a class="btn sm {{ $category === '0' ? '' : 'ghost' }}" href="{{ route('lando.blocks.index', ['category' => 0]) }}">Uncategorized <span class="help">{{ $uncategorized }}</span></a>
    </div>
    <div>
        <div class="panel"><div class="table-wrap"><table>
            <thead><tr><th>Name</th><th>Code</th><th>Category</th><th>Site</th><th class="num">On Pages</th><th>Updated</th><th></th></tr></thead>
            <tbody>@forelse ($blocks as $b)
                <tr><td><b>{{ $b->name }}</b><br><span class="help">{{ \Illuminate\Support\Str::limit(strip_tags($b->html), 70) }}</span></td>
                    <td class="mono">{{ $b->shortcode() }}</td><td>{{ $b->category?->name ?? 'Uncategorized' }}</td><td>{{ $b->site?->name ?? 'All sites' }}</td>
                    <td class="num">{{ $b->components_count }}</td>
                    <td>{{ $b->updated_at->toDateString() }}@if ($b->editor) <span class="muted">· {{ $b->editor->name }}</span>@endif</td>
                    <td><a class="btn sm ghost" href="{{ route('lando.blocks.edit', $b) }}">Edit</a></td></tr>
            @empty <tr><td colspan="7" class="empty">No content blocks in this category.</td></tr> @endforelse</tbody>
        </table></div></div>
    </div>
@endsection
