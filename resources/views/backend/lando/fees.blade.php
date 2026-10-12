@extends('backend.layouts.app')

@section('content')
    <form method="post" action="{{ $action }}" style="display:flex;flex-direction:column;gap:20px">
        @csrf @method('put')
        @include('backend.partials.page-head', ['title' => 'TDSP Fees', 'sub' => 'Utility delivery charges passed through on every bill. Changing a value unlocks its effective date.', 'actions' => '<button class="btn cyan">Save Fees</button>'])
        <div class="panel"><div class="table-wrap"><table>
            <thead><tr><th>Market</th><th>Utility</th><th class="num">Per kWh (¢)</th><th class="num">Per Bill ($)</th><th>Effective</th></tr></thead>
            <tbody>@foreach ($markets as $m)
                @php $f = $fees[$m->id] ?? null; @endphp
                <tr><td class="mono">{{ $m->name }}</td><td>{{ $m->description }}</td>
                    <td class="num"><input type="number" step="0.0001" min="0" name="fees[{{ $m->id }}][per_kwh]" value="{{ $f ? number_format($f->per_kwh, 4, '.', '') : '' }}" data-track data-date-for="eff-{{ $m->id }}" required></td>
                    <td class="num"><input type="number" step="0.01" min="0" name="fees[{{ $m->id }}][per_bill]" value="{{ $f ? number_format($f->per_bill, 2, '.', '') : '' }}" data-track data-date-for="eff-{{ $m->id }}" required></td>
                    <td><input type="date" id="eff-{{ $m->id }}" name="fees[{{ $m->id }}][effective_on]" value="{{ $f?->effective_on->toDateString() }}" min="{{ today()->toDateString() }}" disabled aria-label="Effective date for {{ $m->name }}"></td></tr>
            @endforeach</tbody>
        </table></div></div>
        <p class="help">Sample values. Confirm against the current PUCT-approved tariffs before saving.</p>
    </form>
@endsection
