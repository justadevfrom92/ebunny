@extends('backend.layouts.app')

@section('crumb', $category->exists ? 'Edit '.$category->name : 'New Category')

@section('content')
    @include('backend.partials.page-head', ['title' => $category->exists ? 'Edit Category' : 'New Category', 'sub' => 'A group email templates are filed under.'])
    <form method="post" action="{{ $category->exists ? route('rodeo.emails.categories.update', $category) : route('rodeo.emails.categories.store') }}" class="panel simple-form">@csrf @if ($category->exists) @method('put') @endif
        <div class="panel-body form-grid">
            <label for="name">Name</label><input id="name" name="name" required maxlength="60" value="{{ old('name', $category->name) }}">
            <label for="description">Description</label><input id="description" name="description" maxlength="200" value="{{ old('description', $category->description) }}">
            <label for="color">Color</label><input id="color" type="color" name="color" value="{{ old('color', $category->color) }}" style="height:38px;padding:2px;max-width:80px">
            <label for="position">Order</label><input id="position" type="number" name="position" min="0" max="999" value="{{ old('position', $category->position) }}" placeholder="Last" style="max-width:10ch">
            <label>Templates</label>
            <div class="check-list">@php $picked = old('templates', $category->exists ? $templates->where('email_category_id', $category->id)->pluck('id')->all() : []); @endphp
                @foreach ($templates as $t)<label class="check"><input type="checkbox" name="templates[]" value="{{ $t->id }}" @checked(in_array($t->id, $picked))> {{ $t->name }}@if ($t->category && $t->email_category_id !== $category->id) <span class="muted">· now in {{ $t->category->name }}</span>@endif</label>@endforeach
            </div>
        </div>
        <div class="panel-body actions"><button class="btn cyan">{{ $category->exists ? 'Save Category' : 'Add Category' }}</button><a class="btn ghost" href="{{ route('rodeo.emails.categories') }}">Cancel</a></div>
    </form>
@endsection
