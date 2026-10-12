@extends('admin.layouts.app')

@section('content')
    <form method="post" action="{{ route('astro.rates.update') }}" style="display:flex;flex-direction:column;gap:20px">
        @csrf @method('put')
        @include('admin.partials.page-head', [
            'title' => $onePlan ? 'Update Rates: '.$onePlan->name : 'Update Rates',
            'sub' => 'Energy charges in ¢/kWh by plan and market. Changed cells are highlighted; only those are saved. '
                .($onePlan ? '<a href="'.route('astro.rates.edit').'">Show all plans</a>'
                    : ($showAll ? '<a href="'.route('astro.rates.edit').'">Hide inactive plans</a>' : '<a href="'.route('astro.rates.edit', ['all' => 1]).'">Show inactive plans</a>')),
            'actions' => '<button type="button" class="btn ghost" data-adjust>Adjust All…</button><button class="btn cyan">Save Rates</button>',
        ])
        @if ($onePlan)<input type="hidden" name="plan" value="{{ $onePlan->id }}">@endif
        <div class="panel">
            <div class="panel-body form-row">
                <div class="field"><label for="effective_on">Effective Date</label><input id="effective_on" name="effective_on" type="date" required value="{{ old('effective_on', today()->toDateString()) }}"></div>
                <p class="help" style="margin:0 0 6px">The website switches to new rates on this date. Grid shows the newest scheduled rate.@if ($pending) {{ $pending }} rates are scheduled for a future date.@endif</p>
            </div>
            <div class="table-wrap"><table>
                <thead><tr><th>Plan</th><th>Internal</th>@foreach ($markets as $m)<th class="num">{{ $m->short }}</th>@endforeach</tr></thead>
                <tbody>@foreach ($plans as $p)
                    <tr><td>{{ $p->name }}@unless ($p->active) <span class="pill">inactive</span>@endunless</td><td class="mono">{{ $p->internal }}</td>
                        @foreach ($markets as $m)
                            @php $r = $rates[$p->id.'-'.$m->id] ?? null; @endphp
                            <td class="num"><input type="number" step="0.001" min="0" name="rates[{{ $p->id }}][{{ $m->id }}]" value="{{ $r ? number_format($r->energy, 3, '.', '') : '' }}" data-track data-rate aria-label="{{ $p->name }} {{ $m->short }}"></td>
                        @endforeach
                    </tr>
                @endforeach</tbody>
            </table></div>
        </div>
    </form>
@endsection
