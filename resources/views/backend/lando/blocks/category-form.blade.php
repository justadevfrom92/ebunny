@extends('backend.layouts.app')

@section('crumb', 'New Category')

@section('content')
    @include('backend.partials.page-head', ['title' => 'New Content Block Category', 'sub' => 'Groups content blocks in the list and the page editor.'])
    <form method="post" action="{{ route('lando.blocks.categories.store') }}" class="panel simple-form">@csrf
        <div class="panel-body form-grid">
            <label for="name">Name</label><input id="name" name="name" required maxlength="60" value="{{ old('name') }}">
        </div>
        <div class="panel-body actions"><button class="btn cyan">Add Category</button><a class="btn ghost" href="{{ route('lando.blocks.index') }}">Cancel</a></div>
    </form>
@endsection
