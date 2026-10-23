@extends('backend.layouts.app')

@section('crumb', $u->title)

{{-- One file: details, a preview, download, a new version, edit, delete, and the other files in its folder. --}}
@section('content')
    @php $kb = $u->size >= 1048576 ? number_format($u->size / 1048576, 1).' MB' : number_format(max(0.1, $u->size / 1024), 1).' KB'; @endphp
    @include('backend.partials.page-head', ['title' => $u->title, 'sub' => e($u->category).' · <span class="mono">'.e($u->original_name).'</span> · '.$kb,
        'actions' => '<a class="btn ghost btn-back" href="'.($folder ? route('walker.folder', $folder) : route('walker.uploads.index', ['category' => $u->category])).'">'.e($u->category).'</a>'
            .($exists ? '<a class="btn cyan" href="'.route('walker.uploads.download', $u).'">Download</a>' : '')])

    @unless ($exists)<div class="alert bad"><b>The file is missing from storage.</b> Upload a new version below.</div>@endunless

    <div class="grid-2" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr);align-items:start">
        <div class="panel"><div class="panel-head"><h2>Preview</h2>@if ($preview && $preview['type'] === 'table')@php $n = max(0, count($preview['rows']) - 1); @endphp<span class="muted">{{ $n }} {{ Str::plural('row', $n) }}{{ $n >= 50 ? ' (first 50)' : '' }}</span>@endif</div>
            @if (! $preview)
                <div class="panel-body muted">No preview for .{{ $ext }} files. Download it to open it.</div>
            @elseif ($preview['type'] === 'table')
                <div class="table-wrap" style="max-height:520px;overflow-y:auto"><table>
                    <thead><tr>@foreach ($preview['rows'][0] ?? [] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                    <tbody>@foreach (array_slice($preview['rows'], 1) as $row)<tr>@foreach ($row as $v)<td>{{ $v }}</td>@endforeach</tr>@endforeach</tbody>
                </table></div>
            @elseif ($preview['type'] === 'text')
                <div class="panel-body"><pre class="file-text">{{ $preview['text'] }}</pre></div>
            @elseif ($preview['type'] === 'pdf')
                <iframe class="file-frame" src="{{ route('walker.uploads.view', $u) }}" title="{{ $u->title }}"></iframe>
            @else
                <div class="panel-body"><img src="{{ route('walker.uploads.view', $u) }}" alt="{{ $u->title }}" style="max-width:100%;border:1px solid var(--line);border-radius:6px"></div>
            @endif
        </div>

        <div style="display:flex;flex-direction:column;gap:20px;min-width:0">
            <div class="panel"><div class="panel-head"><h2>File</h2></div><div class="panel-body"><dl class="kv">
                <dt>Name</dt><dd class="mono" style="overflow-wrap:anywhere">{{ $u->original_name }}</dd>
                <dt>Type</dt><dd>.{{ $ext }}</dd>
                <dt>Size</dt><dd>{{ $kb }}</dd>
                <dt>Folder</dt><dd>{{ $u->category }}</dd>
                <dt>Added</dt><dd>{{ $u->created_at->format('n/j/Y g:i A') }}</dd>
                <dt>Last change</dt><dd>{{ $u->updated_at->format('n/j/Y g:i A') }}@if ($u->user)<div class="help">by {{ $u->user->name }}</div>@endif</dd>
                @if ($u->description)<dt>About</dt><dd>{{ $u->description }}</dd>@endif
            </dl></div></div>

            <form method="post" action="{{ route('walker.uploads.update', $u) }}" enctype="multipart/form-data" class="panel">@csrf @method('patch')
                <div class="panel-head"><h2>Edit</h2></div>
                <div class="panel-body" style="display:flex;flex-direction:column;gap:12px">
                    <div class="field"><label for="u-title">Title</label><input id="u-title" name="title" required maxlength="150" value="{{ old('title', $u->title) }}"></div>
                    <div class="field"><label for="up-folder">Folder</label><select name="category" id="up-folder" style="max-width:280px">@foreach (config('walker.upload_categories') as $c)<option @selected(old('category', $u->category) === $c)>{{ $c }}</option>@endforeach</select></div>
                    <div class="field"><label for="u-desc">Description</label><textarea id="u-desc" name="description" maxlength="1000" style="min-height:70px;font-family:inherit">{{ old('description', $u->description) }}</textarea></div>
                    <div class="field"><label for="u-file">New version (optional)</label><input id="u-file" type="file" name="file" accept=".xlsx,.xls,.csv,.pdf,.txt,.zip"><div class="help">Replaces the file; the title and folder stay.</div></div>
                    <div class="actions"><button class="btn cyan">Save</button></div>
                </div>
            </form>
            @can('delete')
                <form method="post" action="{{ route('walker.uploads.destroy', $u) }}" data-confirm="Delete {{ $u->title }}?|The file is removed for everyone.|Delete">@csrf @method('delete')<button class="btn red">Delete File</button></form>
            @endcan
        </div>
    </div>

    @if ($siblings->isNotEmpty())
        <div class="panel"><div class="panel-head"><h2>Other Files in {{ $u->category }}</h2></div><div class="table-wrap"><table>
            <tbody>@foreach ($siblings as $s)<tr class="click" data-href="{{ route('walker.uploads.show', $s) }}"><td><a href="{{ route('walker.uploads.show', $s) }}"><b>{{ $s->title }}</b></a><div class="help mono">{{ $s->original_name }}</div></td><td class="nowrap">{{ $s->created_at->format('n/j/Y') }}</td></tr>@endforeach</tbody>
        </table></div></div>
    @endif
@endsection
