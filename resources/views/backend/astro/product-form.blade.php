@extends('backend.layouts.app')

@section('crumb', $product->exists ? $product->name : 'New Product')

{{-- A Build Your Own Plan add-on: what the website shows, where, and its price. --}}
@section('content')
    @include('backend.partials.page-head', ['title' => $product->exists ? $product->name : 'New Product',
        'sub' => 'An add-on on the website\'s Build Your Own Plan page. It shows on its step\'s tab with the name and line below.',
        'actions' => '<a class="btn ghost btn-back" href="'.route('astro.products.index').'">Back to Build Your Own</a>'])

    <form method="post" action="{{ $product->exists ? route('astro.products.save', $product) : route('astro.products.store') }}" class="panel">@csrf @if ($product->exists) @method('put') @endif
        <div class="panel-body form-grid" style="grid-template-columns:1fr">
            <label for="name">Name</label><input id="name" name="name" required maxlength="100" value="{{ old('name', $product->name) }}" placeholder="e.g. Home Energy Audit">
            <label for="description">Line under the name</label><input id="description" name="description" maxlength="300" value="{{ old('description', $product->description) }}" placeholder="One sentence about what the customer gets">
            <label for="key">Key</label><div><input id="key" name="key" maxlength="40" value="{{ old('key', $product->key) }}" placeholder="made from the name if left empty" @disabled($product->exists)>
                <div class="help">Used by the website and orders. It can't change after the product is made.</div></div>
            <label for="step">Step on the website</label><select id="step" name="step">@foreach (\App\Models\ByopProduct::STEPS as $s)<option @selected(old('step', $product->step) === $s)>{{ $s }}</option>@endforeach</select>
            <label for="type">Type</label><select id="type" name="type">@foreach (\App\Models\ByopProduct::TYPES as $t)<option @selected(old('type', $product->type) === $t)>{{ $t }}</option>@endforeach</select>
            <label for="rate_adj">Rate change (¢/kWh)</label><div><input id="rate_adj" type="number" step="0.01" min="-10" max="10" name="rate_adj" required value="{{ old('rate_adj', number_format((float) $product->rate_adj, 2, '.', '')) }}">
                <div class="help">Negative is a discount.</div></div>
            <label for="monthly">Monthly price ($)</label><input id="monthly" type="number" step="0.01" min="0" max="500" name="monthly" required value="{{ old('monthly', number_format((float) $product->monthly, 2, '.', '')) }}">
            <label for="active">Website</label><label class="check"><input type="hidden" name="active" value="0"><input id="active" type="checkbox" name="active" value="1" @checked(old('active', $product->active))> Show on the Build Your Own Plan page</label>
        </div>
        <div class="panel-body"><button class="btn cyan">{{ $product->exists ? 'Save Product' : 'Add Product' }}</button></div>
    </form>
@endsection
