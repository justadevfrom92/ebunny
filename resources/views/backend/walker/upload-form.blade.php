@extends('backend.layouts.app')

@section('crumb', 'Upload a File')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Upload a File', 'sub' => 'Excel, CSV, PDF, text or zip, up to 20 MB.'])
    <form method="post" action="{{ route('walker.uploads.store') }}" enctype="multipart/form-data" class="panel simple-form">@csrf
        <div class="panel-body form-grid">
            <label for="u-title">Title</label><input id="u-title" name="title" required value="{{ old('title') }}">
            <label for="up-folder">Folder</label><select name="category" id="up-folder" required style="max-width:280px">@foreach (config('walker.upload_categories') as $c)<option @selected(old('category', request('category', 'Other')) === $c)>{{ $c }}</option>@endforeach</select>
            <label for="u-desc">Description</label><textarea id="u-desc" name="description" style="min-height:70px;font-family:inherit">{{ old('description') }}</textarea>
            <label for="u-file">File</label><input id="u-file" type="file" name="file" required accept=".xlsx,.xls,.csv,.pdf,.txt,.zip">
        </div>
        <div class="panel-body actions"><button class="btn cyan">Upload</button><a class="btn ghost" href="{{ route('walker.uploads.index') }}">Cancel</a></div>
    </form>
@endsection
