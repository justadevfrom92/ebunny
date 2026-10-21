{{-- A big approval card: title, %, the 👍/👎 bar and what it's out of. $a = ['rated', 'good', 'pct'], $title --}}
<div class="panel"><div class="panel-head"><h2>{{ $title }}</h2>@isset($note)<span class="help">{{ $note }}</span>@endisset</div><div class="panel-body ap-card">
    @if ($a['pct'] === null)<span class="muted">No ratings yet</span>
    @else
        <b class="approval {{ $a['pct'] >= 90 ? 'ok' : ($a['pct'] >= 75 ? 'warn' : 'bad') }}">{{ rtrim(rtrim(number_format($a['pct'], 1), '0'), '.') }}%</b>
        <span class="ap-split" aria-hidden="true"><span style="width:{{ $a['pct'] }}%"></span></span>
        <div class="ap-legend"><span>👍 {{ number_format($a['good']) }} helped</span><span>👎 {{ number_format($a['rated'] - $a['good']) }} didn't</span></div>
        <span class="help">{{ number_format($a['rated']) }} rated {{ Str::plural('ticket', $a['rated']) }}</span>
    @endif
</div></div>
