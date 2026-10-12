@extends('backend.layouts.app')

@section('content')
    <form method="post" action="{{ route('bounty.offers.save') }}" style="display:flex;flex-direction:column;gap:20px">@csrf @method('put')
        @include('backend.partials.page-head', ['title' => 'Offers', 'sub' => 'What customers can spend stars on. Corral (Spend Stars) and My Account (My Rewards) show the active offers.',
            'actions' => '<button type="button" class="btn ghost" data-clone="#offer-row" data-into="#offer-rows">Add an Offer</button><button class="btn cyan">Save Offers</button>'])
        @error('o')<div class="alert bad">{{ $message }}</div>@enderror
        <div class="panel"><div class="table-wrap"><table>
            <thead><tr><th>Offer</th><th>Description</th><th class="num">Stars</th><th>Effect</th><th>Value</th><th>Active</th></tr></thead>
            <tbody id="offer-rows">@foreach ($offers as $o)
                <tr><td><input class="cell-input" name="o[{{ $o->id }}][name]" value="{{ $o->name }}" required aria-label="Offer name" data-track></td>
                    <td><input class="cell-input" name="o[{{ $o->id }}][description]" value="{{ $o->description }}" required aria-label="Description" data-track></td>
                    <td class="num"><input type="number" min="0" name="o[{{ $o->id }}][stars]" value="{{ $o->stars }}" required aria-label="Stars" data-track style="width:9ch"></td>
                    <td><select name="o[{{ $o->id }}][effect]" aria-label="Effect">@foreach (\App\Models\RewardOffer::EFFECTS as $k => $l)<option value="{{ $k }}" @selected($o->effect === $k)>{{ $l }}</option>@endforeach</select></td>
                    <td><input class="cell-input" name="o[{{ $o->id }}][value]" value="{{ $o->value }}" aria-label="Value" placeholder="$ or product" style="width:12ch" data-track></td>
                    <td><input type="hidden" name="o[{{ $o->id }}][active]" value="0"><label class="check"><input type="checkbox" name="o[{{ $o->id }}][active]" value="1" @checked($o->active)> Active</label></td></tr>
            @endforeach</tbody>
        </table></div></div>
        <p class="help">Value: the dollar amount for a bill credit, or the product name (e.g. ecobee) for a product. Bill credits go to Caboose → Credits &amp; Debits to be applied.</p>
    </form>
    <template id="offer-row"><tr>
        <td><input class="cell-input" name="o[__i__][name]" required aria-label="Offer name"></td><td><input class="cell-input" name="o[__i__][description]" required aria-label="Description"></td>
        <td class="num"><input type="number" min="0" name="o[__i__][stars]" required aria-label="Stars" style="width:9ch"></td>
        <td><select name="o[__i__][effect]" aria-label="Effect">@foreach (\App\Models\RewardOffer::EFFECTS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></td>
        <td><input class="cell-input" name="o[__i__][value]" aria-label="Value" style="width:12ch"></td>
        <td><input type="hidden" name="o[__i__][active]" value="1">New</td></tr></template>
@endsection
