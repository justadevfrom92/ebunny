@props(['bars', 'unit' => '', 'decimals' => 0, 'height' => 170])
{{--
  A bar chart used everywhere (admin and website): the amount is written on every bar, the column
  label sits under every bar, and hovering or focusing a bar shows "label: amount" straight away.
  $bars: list of ['label' => …, 'value' => …, 'tip' => optional longer label for the tooltip]
--}}
@php
    $bars = collect($bars)->values();
    $top = max(1, (float) $bars->max('value'));
    $fmt = fn ($v) => number_format((float) $v, $decimals);
    $step = max(1, (int) ceil($bars->count() / 16));   // thin out column labels when there are many bars
@endphp
<div class="bc" style="--bc-n:{{ max(1, $bars->count()) }};--bc-h:{{ $height }}px" role="img" aria-label="{{ $bars->map(fn ($b) => ($b['tip'] ?? $b['label']).': '.$fmt($b['value']).($unit ? ' '.$unit : ''))->implode('; ') }}">
    @foreach ($bars as $i => $b)
        <div class="bc-col" tabindex="0" data-tip="{{ ($b['tip'] ?? $b['label']).': '.$fmt($b['value']).($unit ? ' '.$unit : '') }}">
            <span class="bc-val">{{ $fmt($b['value']) }}</span>
            <span class="bc-track"><b style="height:{{ round((float) $b['value'] / $top * 100, 1) }}%"></b></span>
            <span class="bc-lab">{{ $i % $step === 0 ? $b['label'] : '' }}</span>
        </div>
    @endforeach
</div>
@once
<style>
.bc { display: grid; grid-template-columns: repeat(var(--bc-n), minmax(0, 1fr)); gap: 4px; height: var(--bc-h); align-items: stretch; }
.bc-col { display: flex; flex-direction: column; align-items: center; min-width: 0; outline: none; cursor: default; }
.bc-val { font-size: .68rem; font-weight: 600; line-height: 1.3; color: inherit; white-space: nowrap; }
.bc-track { flex: 1; width: 100%; display: flex; align-items: flex-end; border-bottom: 1px solid rgba(127,127,127,.35); }
.bc-track b { display: block; width: 100%; min-height: 2px; border-radius: 3px 3px 0 0; background: var(--bc-color, #00AEEF); transition: filter .1s; }
.bc-col:hover b, .bc-col:focus-visible b { filter: brightness(.85); }
.bc-col:focus-visible { box-shadow: 0 0 0 2px #00AEEF; border-radius: 3px; }
.bc-lab { font-size: .66rem; line-height: 1.2; height: 1.4em; padding-top: 3px; opacity: .75; white-space: nowrap; }
@media (max-width: 600px) { .bc-val { font-size: .56rem; } .bc { gap: 2px; } }
</style>
@endonce
@include('components.chart-tip')
