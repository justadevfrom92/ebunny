{{-- The last 24 hours as 96 blocks of 15 minutes. Click (or Enter on) a block to see what happened in it.
     $blocks, $mode: 'all' (every check: what wasn't healthy) or 'check' (one check: each result in the block) --}}
@php
    $labels = \App\Support\Health::LABELS;
    $data = $blocks->values()->map(fn ($b) => ['t' => $b['from']->format('M j, g:i A').' – '.$b['from']->copy()->addMinutes(15)->format('g:i A'), 'n' => $b['n'], 's' => $b['status'],
        'rows' => $b['rows'], 'actions' => $b['actions']]);
@endphp
<div class="hb-strip" data-hb-mode="{{ $mode }}" role="group" aria-label="Last 24 hours: pick a 15-minute block to see it">@foreach ($blocks->values() as $i => $b)<i class="hb-{{ $b['status'] }}" role="button" tabindex="0" data-i="{{ $i }}" aria-label="{{ $b['from']->format('g:i A') }}" data-tip="{{ $b['from']->format('g:i A') }}–{{ $b['from']->copy()->addMinutes(15)->format('g:i A') }}: {{ $b['n'] ? ($labels[$b['status']][0] ?? $b['status']) : 'no check ran' }} · click for details"></i>@endforeach</div>
<script type="application/json" class="hb-data">@json(['blocks' => $data, 'labels' => $labels])</script>
<div class="hb-axis"><span>24 hours ago</span><span>12 hours ago</span><span>now</span></div>
<div class="hour-legend"><span><i class="hb-dot hb-ok"></i> Healthy</span><span><i class="hb-dot hb-warn"></i> Warning</span><span><i class="hb-dot hb-bad"></i> Problem</span><span><i class="hb-dot hb-down"></i> Taken down</span><span><i class="hb-dot hb-none"></i> No check ran</span><span class="muted">Click a block to see it</span></div>
<div class="hb-detail" hidden></div>
