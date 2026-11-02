@extends('backend.layouts.app')

@section('crumb', $part->exists ? $part->name : 'New PDF '.\App\Models\PdfPart::KINDS[$part->kind])

{{-- One PDF header or footer: its wording (fill-ins work), whether it's the default, a preview on a sample letter, and the templates using it. --}}
@section('content')
    @php $label = \App\Models\PdfPart::KINDS[$part->kind]; @endphp
    @include('backend.partials.page-head', ['title' => $part->exists ? $part->name : 'New PDF '.$label,
        'sub' => $part->exists ? 'PDF '.strtolower($label).' #'.$part->id.($part->is_default ? ' · the default' : '').' · updated '.$part->updated_at->format('n/j/Y g:i A') : 'Save it, then pick it on a PDF template (or make it the default).',
        'actions' => '<a class="btn ghost btn-back" href="'.route('rodeo.pdf-parts').'">Back to PDF Headers & Footers</a>'])

    <div style="display:flex;flex-direction:column;gap:20px">
        <form method="post" action="{{ $part->exists ? route('rodeo.pdf-parts.update', $part) : route('rodeo.pdf-parts.store') }}" class="panel">@csrf @if ($part->exists) @method('put') @else<input type="hidden" name="kind" value="{{ $part->kind }}">@endif
            <div class="panel-head"><h2>{{ $label }}</h2></div>
            <div class="panel-body form-grid" style="grid-template-columns:1fr">
                <label for="name">Name</label><input id="name" name="name" required maxlength="100" value="{{ old('name', $part->name) }}">
                @if ($part->kind === 'header')
                    <label for="title">Big text in the band</label><input id="title" name="title" maxlength="120" placeholder="{{ config('brand.name') }}" value="{{ old('title', $part->title) }}">
                    <label for="subtitle">Line under it</label><input id="subtitle" name="subtitle" maxlength="160" placeholder="Blank: the PDF's title" value="{{ old('subtitle', $part->subtitle) }}">
                    <label for="color">Look</label><select id="color" name="color">@foreach (\App\Models\PdfPart::COLORS as $k => $l)<option value="{{ $k }}" @selected(old('color', $part->color) === $k)>{{ $l }}</option>@endforeach</select>
                @else
                    <label for="body">Small print on every page</label><textarea id="body" name="body" maxlength="600" style="min-height:90px">{{ old('body', $part->body) }}</textarea>
                    <span class="help">A PDF template's own footer text, when it has one, is used instead.</span>
                @endif
                <label for="is_default">Default</label><label class="check"><input type="hidden" name="is_default" value="0"><input id="is_default" type="checkbox" name="is_default" value="1" @checked(old('is_default', $part->is_default))> Use this {{ strtolower($label) }} on templates that don't pick one</label>
                <span class="help">Fill-ins like <span class="mono">&#123;&#123;brand&#125;&#125;</span>, <span class="mono">&#123;&#123;phone&#125;&#125;</span> or <span class="mono">&#123;&#123;account&#125;&#125;</span> work here too.</span>
            </div>
            <div class="panel-body"><button class="btn cyan">Save {{ $label }}</button></div>
        </form>
        @if ($part->exists && ! $part->is_default)
            <form method="post" action="{{ route('rodeo.pdf-parts.destroy', $part) }}" data-confirm="Delete {{ $part->name }}?|Templates using it go back to the default {{ strtolower($label) }}.|Delete" style="margin-top:-8px">@csrf @method('delete')<button class="btn sm ghost">Delete {{ $label }}</button></form>
        @endif

        <div class="panel"><div class="panel-head"><h2>Preview</h2><span class="muted">on a sample letter{{ $part->exists ? '' : ' (save to see your changes)' }}</span></div>
            @include('backend.rodeo.pdfs._pages', ['pages' => $pages, 'pdf64' => $pdf64, 'name' => 'sample.pdf'])</div>

        @if ($part->exists)
        <div class="panel"><div class="panel-head"><h2>PDF Templates Using It</h2></div><div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Template</th><th>Category</th><th>Status</th></tr></thead>
            <tbody>@forelse ($templates as $t)
                @php $u = route('rodeo.pdfs.edit', $t); @endphp
                <tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $t->id }}</b></a></td><td>{{ $t->name }}</td><td>{{ $t->category ?? '—' }}</td><td>{{ $t->status }}</td></tr>
            @empty <tr><td colspan="4" class="empty">No templates use it yet.</td></tr> @endforelse</tbody>
        </table></div></div>
        @endif
    </div>
@endsection
