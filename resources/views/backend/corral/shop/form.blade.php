@extends('backend.layouts.app')

@section('crumb', $p->exists ? 'Edit '.$p->name : 'New Product')

{{-- Add or edit a website Shop product. --}}
@section('content')
    @include('backend.partials.page-head', ['title' => $p->exists ? 'Edit '.$p->name : 'New Product', 'sub' => 'A product in the website Shop. Protection plans (PR-…) are billed monthly; everything else is charged when ordered.',
        'actions' => '<a class="btn ghost btn-back" href="'.($p->exists ? route('corral.shop.show', $p) : route('corral.shop.index')).'">Back</a>'])

    <form method="post" action="{{ $p->exists ? route('corral.shop.update', $p) : route('corral.shop.store') }}" class="panel">@csrf @if ($p->exists) @method('put') @endif
        <div class="panel-body form-grid" style="grid-template-columns:1fr">
            <label for="name">Name</label><input id="name" name="name" required maxlength="120" value="{{ old('name', $p->name) }}">
            <label for="sku">SKU</label><input id="sku" name="sku" required maxlength="20" value="{{ old('sku', $p->sku) }}" placeholder="e.g. PL-260">
            <label for="cat">Category</label><select id="cat" name="cat">@foreach ($cats as $k => $label)<option value="{{ $k }}" @selected(old('cat', $p->cat) === $k)>{{ $label }}</option>@endforeach</select>
            <label for="price">Price ($)</label><input id="price" type="number" step="0.01" min="0" name="price" required value="{{ old('price', $p->price) }}">
            <label for="was">Was ($, for a sale)</label><input id="was" type="number" step="0.01" min="0" name="was" value="{{ old('was', $p->was) }}" placeholder="leave empty if not on sale">
            <label for="stars">Rewards stars earned</label><input id="stars" type="number" min="0" name="stars" required value="{{ old('stars', $p->stars ?? 0) }}">
            <label for="badge">Badge</label><input id="badge" name="badge" maxlength="40" value="{{ old('badge', $p->badge) }}" placeholder="e.g. Best Seller">
            <label for="icon">Picture</label><select id="icon" name="icon">@foreach (\App\Models\ShopProduct::ICONS as $i)<option @selected(old('icon', $p->icon) === $i)>{{ $i }}</option>@endforeach</select>
            <label for="blurb">Description</label><textarea id="blurb" name="blurb" maxlength="300" style="min-height:70px">{{ old('blurb', $p->blurb) }}</textarea>
            <label for="stock">Stock</label><label class="check"><input type="hidden" name="stock" value="0"><input id="stock" type="checkbox" name="stock" value="1" @checked(old('stock', $p->stock))> In stock</label>
            <label for="active">Website</label><label class="check"><input type="hidden" name="active" value="0"><input id="active" type="checkbox" name="active" value="1" @checked(old('active', $p->active))> Show in the Shop</label>
        </div>
        <div class="panel-body"><button class="btn cyan">{{ $p->exists ? 'Save Product' : 'Add Product' }}</button></div>
    </form>
@endsection
