@extends('backend.layouts.app')

@section('content')
    <form method="post" action="{{ route('astro.rates.update') }}" style="display:flex;flex-direction:column;gap:20px">
        @csrf @method('put')
        @include('backend.partials.page-head', [
            'title' => $onePlan ? 'Update Rates: '.$onePlan->name : 'Update Rates',
            'sub' => 'Energy charges in ¢/kWh by plan and market. Changed cells are highlighted; only those are saved. '
                .($onePlan ? '<a href="'.route('astro.rates.edit').'">Show all plans</a>'
                    : ($showAll ? '<a href="'.route('astro.rates.edit').'">Hide inactive plans</a>' : '<a href="'.route('astro.rates.edit', ['all' => 1]).'">Show inactive plans</a>')),
            'actions' => '<button type="button" class="btn ghost" data-open-dialog="rate-upload">Upload Rates</button><button type="button" class="btn ghost" data-adjust>Adjust All…</button><button class="btn cyan">Save Rates</button>',
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

    {{-- Rate files uploaded: each is read in the background, then waits for someone to confirm it --}}
    @if ($imports->isNotEmpty())
        <div class="panel" style="margin-top:20px"><div class="panel-head"><h2>Rate Uploads</h2><span class="muted">confirm a file to make its rates live</span></div><div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Uploaded</th><th>File</th><th>By</th><th class="num">Changes</th><th>Status</th></tr></thead>
            <tbody>@foreach ($imports as $i)
                @php $u = route('astro.rates.imports.show', $i); [$label, $tone] = \App\Models\RateImport::LABELS[$i->status]; @endphp
                <tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $i->id }}</b></a></td><td class="nowrap">{{ $i->created_at->format('n/j/Y g:i A') }}</td>
                    <td class="mono wrap">{{ $i->file_name }}</td><td>{{ $i->user?->name ?? '—' }}</td><td class="num">{{ $i->changes !== null ? count($i->changes) : '—' }}</td>
                    <td>@include('backend.partials.pill', ['text' => $label, 'tone' => $tone])</td></tr>
            @endforeach</tbody>
        </table></div></div>
    @endif

    {{-- Upload Rates: pick a file, then Update Rates reads it in the background --}}
    <dialog id="rate-upload" class="modal" aria-labelledby="rate-upload-title">
        <form method="post" action="{{ route('astro.rates.imports.store') }}" enctype="multipart/form-data">@csrf
            <div class="modal-head"><h2 id="rate-upload-title">Upload Rates</h2><button type="button" class="modal-x" data-close-dialog aria-label="Close">×</button></div>
            <div class="modal-body">
                <p class="help" style="margin-top:0">A CSV or Excel file with <b>Plan, Market, Rate</b> columns, or the same grid as this page: Plan, then one column per market ({{ $markets->pluck('short')->implode(', ') }}). Rates are ¢/kWh.
                    <a href="{{ route('astro.rates.imports.template') }}">Download the current rates</a> to start from.</p>
                <label class="file-pick"><input type="file" name="file" accept=".csv,.txt,.xlsx" required data-file-pick><span data-file-name>Choose a file…</span></label>
                <p class="help">It runs in the background. Nothing changes until you look over the changes and confirm them.</p>
            </div>
            <div class="modal-foot"><button type="button" class="btn ghost" data-close-dialog>Cancel</button><button class="btn cyan" data-file-submit disabled>Update Rates</button></div>
        </form>
    </dialog>
@endsection
