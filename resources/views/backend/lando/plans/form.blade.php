@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => $plan->exists ? 'Editing '.$plan->name : 'Add a Plan', 'sub' => $plan->exists ? 'Plan ID '.$plan->id : null])

    <form method="post" action="{{ $plan->exists ? app_route('plans.update', $plan) : app_route('plans.store') }}" class="panel"><div class="panel-body">
        @csrf @if ($plan->exists) @method('put') @endif
        <div class="form-grid">
            <label for="name">Plan Name</label><input id="name" name="name" required value="{{ old('name', $plan->name) }}" @unless ($plan->exists) data-slug-source @endunless>
            <label for="internal">Internal Name</label><input id="internal" name="internal" required class="mono" value="{{ old('internal', $plan->internal) }}">
            <label for="slug">URL Slug</label><input id="slug" name="slug" class="mono" value="{{ old('slug', $plan->slug) }}" placeholder="plan-name-12" data-slug-target>
            <label for="active">Status</label>
            <select id="active" name="active"><option value="1" @selected(old('active', $plan->active))>Active</option><option value="0" @selected(! old('active', $plan->active))>Inactive</option></select>
            <label for="type">Rate Class</label>
            <select id="type" name="type">@foreach (['Resi', 'Biz'] as $t)<option @selected(old('type', $plan->type) === $t)>{{ $t }}</option>@endforeach</select>
            <label for="term">Term (months)</label><input id="term" name="term" type="number" min="1" max="60" value="{{ old('term', $plan->term) }}">
            <label for="rolloff">Rolloff Plan</label>
            <select id="rolloff" name="rolloff"><option>None</option>@foreach ($rolloffs as $r)<option @selected(old('rolloff', $plan->rolloff) === $r)>{{ $r }}</option>@endforeach</select>
            <label for="etf">Early Termination Fee</label><input id="etf" name="etf" value="{{ old('etf', $plan->etf) }}">
            <label for="mrc">Monthly Base Charge</label><input id="mrc" name="mrc" type="number" step="0.01" min="0" value="{{ old('mrc', $plan->mrc) }}">
            <label for="green">Renewable %</label><input id="green" name="green" type="number" min="0" max="100" value="{{ old('green', $plan->green) }}">
            <label for="tags">Website Filters</label><input id="tags" name="tags" value="{{ old('tags', implode(', ', $plan->tags ?? [])) }}" placeholder="fixed, green, flex, month">
            <label for="perks">Website Bullets</label><textarea id="perks" name="perks" style="min-height:100px;font-family:inherit" placeholder="One per line">{{ old('perks', implode("\n", $plan->perks ?? [])) }}</textarea>
        </div>
        <div class="actions" style="margin-top:20px"><button class="btn cyan">Save Plan</button><a class="btn ghost" href="{{ app_route('plans.index') }}">Cancel</a></div>
    </div></form>
@endsection
