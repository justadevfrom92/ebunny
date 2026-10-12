@extends('backend.layouts.app')

@section('crumb', $template->exists ? 'Edit Template' : 'Add a Template')

@section('content')
    @include('backend.partials.page-head', ['title' => $template->exists ? 'Edit Template: '.$template->name : 'Add a Template'])

    <form method="post" action="{{ $template->exists ? route('lando.templates.update', $template) : route('lando.templates.store') }}" class="panel"><div class="panel-body">
        @csrf @if ($template->exists) @method('put') @endif
        <div class="form-grid">
            <label for="name">Name</label><input id="name" name="name" required class="mono" value="{{ old('name', $template->name) }}" placeholder="e.g. article">
            <label for="description">Description</label><input id="description" name="description" required value="{{ old('description', $template->description) }}">
            <label for="file">Layout File</label><input id="file" name="file" class="mono" value="{{ old('file', $template->file) }}" placeholder="shared/layouts/article.html (optional)">
        </div>
        <div class="actions" style="margin-top:20px"><button class="btn cyan">Save Template</button><a class="btn ghost" href="{{ route('lando.templates.index') }}">Cancel</a></div>
    </div></form>

    @if ($template->exists)
        <div class="panel"><div class="panel-head"><h2>Pages Using This Template</h2><span class="muted">{{ $template->pages_count }}</span></div>
            <div class="table-wrap"><table><thead><tr><th>Page</th><th>Path</th><th>Status</th></tr></thead><tbody>
                @forelse ($template->pages as $p)
                    <tr><td><a href="{{ route('lando.pages.edit', $p) }}">{{ $p->title }}</a></td><td class="mono">{{ $p->url() }}</td><td>{{ $p->status }}</td></tr>
                @empty <tr><td colspan="3" class="empty">No pages use this template yet.</td></tr> @endforelse
            </tbody></table></div></div>
    @endif
@endsection
