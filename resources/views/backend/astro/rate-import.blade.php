@extends('backend.layouts.app')

@section('crumb', 'Rate Upload #'.$import->id)

{{-- One rate file: while it's being read, this page checks back on its own; then the changes wait for Confirm (with an effective date) or Discard. --}}
@section('content')
    @php
        [$label, $tone] = \App\Models\RateImport::LABELS[$import->status];
        $changes = collect($import->changes ?? []);
        $problems = collect($import->problems ?? []);
    @endphp
    @include('backend.partials.page-head', ['title' => 'Rate Upload #'.$import->id, 'sub' => e($import->file_name).' · uploaded '.$import->created_at->format('n/j/Y g:i A').' by '.e($import->user?->name ?? '—'),
        'actions' => '<a class="btn ghost btn-back" href="'.route('astro.rates.edit').'">Back to Update Rates</a>'])

    <div id="rate-import" style="display:flex;flex-direction:column;gap:20px" @if ($import->running()) data-refresh @endif>
        <div class="hb-banner hb-{{ ['ready' => 'warn', 'applied' => 'ok', 'failed' => 'bad'][$import->status] ?? 'none' }}">
            <div><b>{{ $label }}</b><span>@switch($import->status)
                @case('queued') @case('processing') The file is being read in the background. This page updates on its own. @break
                @case('ready') Look over the changes below. Nothing is live until you confirm. @break
                @case('failed') {{ $import->error }} @break
                @case('applied') {{ $changes->count() }} rates went live, effective {{ $import->effective_on?->format('F j, Y') }}. Confirmed by {{ $import->applier?->name ?? '—' }} {{ $import->applied_at?->format('n/j/Y g:i A') }}. @break
                @default Discarded: no rates changed.
            @endswitch</span></div>
        </div>

        @unless ($import->running() || $import->status === 'failed')
            <div class="grid-4 stat-row">
                <div class="stat"><span>Rates in the File</span><strong>{{ number_format($import->rows) }}</strong></div>
                <div class="stat"><span>Will Change</span><strong>{{ number_format($changes->count()) }}</strong></div>
                <div class="stat"><span>Already the Same</span><strong>{{ number_format($import->unchanged) }}</strong></div>
                <div @class(['stat', 'alert' => $problems->isNotEmpty()])><span>Problems</span><strong>{{ number_format($problems->count()) }}</strong><small>left out</small></div>
            </div>
        @endunless

        @if ($import->status === 'ready')
            <div class="panel"><div class="panel-head"><h2>Confirm These Rates</h2></div>
                <div class="panel-body">
                    @if ($changes->isEmpty())
                        <p style="margin:0">Nothing would change: every rate in the file matches the current rate.</p>
                    @else
                        <form method="post" action="{{ route('astro.rates.imports.confirm', $import) }}" class="form-row" data-confirm="Make {{ $changes->count() }} rates live?|The website switches to these rates on the effective date.|Confirm Rates">@csrf
                            <div class="field"><label for="effective_on">Effective Date</label><input id="effective_on" name="effective_on" type="date" required min="{{ today()->toDateString() }}" value="{{ old('effective_on', today()->toDateString()) }}"></div>
                            <button class="btn cyan">Confirm Rates</button>
                        </form>
                        @error('effective_on')<p class="help" style="color:var(--bad)">{{ $message }}</p>@enderror
                        @if ($problems->isNotEmpty())<p class="help">The {{ $problems->count() }} {{ Str::plural('line', $problems->count()) }} with problems are left out.</p>@endif
                    @endif
                    <form method="post" action="{{ route('astro.rates.imports.discard', $import) }}" style="margin-top:12px">@csrf<button class="btn sm ghost">Discard This File</button></form>
                </div>
            </div>
        @endif

        @if ($changes->isNotEmpty())
            <div class="panel"><div class="panel-head"><h2>{{ $import->status === 'applied' ? 'Rates Changed' : 'Rates That Will Change' }}</h2><span class="muted">¢/kWh</span></div><div class="table-wrap"><table>
                <thead><tr><th>Plan</th><th>Market</th><th class="num">Now</th><th class="num">New</th><th class="num">Change</th></tr></thead>
                <tbody>@foreach ($changes as $c)
                    @php $d = $c['old'] !== null ? $c['new'] - $c['old'] : null; @endphp
                    <tr><td><b>{{ $c['plan'] }}</b><div class="help mono">{{ $c['internal'] }}</div></td><td class="mono">{{ $c['market'] }}</td>
                        <td class="num">{{ $c['old'] !== null ? number_format($c['old'], 3) : 'none' }}</td><td class="num"><b>{{ number_format($c['new'], 3) }}</b></td>
                        <td class="num" @style(['color:var(--bad)' => $d > 0, 'color:var(--ok)' => $d < 0])>{{ $d === null ? 'new' : ($d > 0 ? '+' : '').number_format($d, 3) }}</td></tr>
                @endforeach</tbody>
            </table></div></div>
        @endif

        @if ($problems->isNotEmpty())
            <div class="panel"><div class="panel-head"><h2>Problems</h2><span class="muted">these lines are left out</span></div><div class="table-wrap"><table>
                <thead><tr><th class="num">Line</th><th>Problem</th></tr></thead>
                <tbody>@foreach ($problems as $p)<tr class="row-warn"><td class="num">{{ $p['line'] }}</td><td>{{ $p['message'] }}</td></tr>@endforeach</tbody>
            </table></div></div>
        @endif
    </div>

    @if ($import->running())
        <script>setTimeout(function () { location.reload(); }, 3000);   // check back until the file is read</script>
    @endif
@endsection
