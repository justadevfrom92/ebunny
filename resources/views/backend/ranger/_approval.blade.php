{{-- An approval % with its counts. $a = ['rated', 'good', 'pct'] --}}
@if ($a['pct'] === null)<span class="muted">No ratings yet</span>
@else<b class="approval {{ $a['pct'] >= 90 ? 'ok' : ($a['pct'] >= 75 ? 'warn' : 'bad') }}">{{ rtrim(rtrim(number_format($a['pct'], 1), '0'), '.') }}%</b> <span class="help">{{ $a['good'] }} of {{ $a['rated'] }} 👍</span>@endif
