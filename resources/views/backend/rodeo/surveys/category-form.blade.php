@extends('backend.layouts.app')

@section('crumb', $category->exists ? 'Edit '.$category->name : 'New Category')

@section('content')
    @include('backend.partials.page-head', ['title' => $category->exists ? 'Edit Category' : 'New Category', 'sub' => 'A topic survey questions are filed under.'])
    <form method="post" action="{{ $category->exists ? route('rodeo.surveys.categories.update', $category) : route('rodeo.surveys.categories.store') }}" class="panel simple-form">@csrf @if ($category->exists) @method('put') @endif
        <div class="panel-body form-grid">
            <label for="name">Name</label><input id="name" name="name" required maxlength="60" value="{{ old('name', $category->name) }}">
            <label for="description">Description</label><input id="description" name="description" maxlength="200" value="{{ old('description', $category->description) }}">
            <label for="color">Color</label><input id="color" type="color" name="color" value="{{ old('color', $category->color) }}" style="height:38px;padding:2px;max-width:80px">
            <label for="position">Order</label><input id="position" type="number" name="position" min="0" max="999" value="{{ old('position', $category->position) }}" placeholder="Last" style="max-width:10ch">
        </div>
        <div class="panel-body actions"><button class="btn cyan">{{ $category->exists ? 'Save Category' : 'Add Category' }}</button><a class="btn ghost" href="{{ route('rodeo.surveys.categories') }}">Cancel</a></div>
    </form>
@endsection
