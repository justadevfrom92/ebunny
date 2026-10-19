@extends('backend.layouts.app')

@section('crumb', 'Heat Map: '.$name)

{{-- Click heat map: the email as sent, each link shaded by how often it was clicked, and the counts per link. --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'Click Heat Map', 'sub' => e($name).' · '.number_format($sent).' sent · '.number_format($opened).' opened · '.number_format($clickers).' clicked',
        'actions' => '<a class="btn ghost btn-back" href="'.$back.'">Back</a>'])

    <div class="grid-4 stat-row">
        <div class="stat"><span>Clicks</span><strong>{{ number_format($total) }}</strong><small>on {{ $counts->count() }} {{ Str::plural('link', $counts->count()) }}</small></div>
        <div class="stat"><span>Click Rate</span><strong>{{ $sent ? round(100 * $clickers / $sent, 1) : 0 }}%</strong><small>of emails sent</small></div>
        <div class="stat"><span>Click-to-Open</span><strong>{{ $opened ? round(100 * $clickers / $opened, 1) : 0 }}%</strong><small>of emails opened</small></div>
        <div class="stat"><span>Devices</span><strong style="font-size:1rem">@forelse ($devices as $d => $n){{ $d }} {{ $total ? round(100 * $n / $total) : 0 }}%@if (! $loop->last) · @endif @empty — @endforelse</strong></div>
    </div>

    <div class="grid-2" style="grid-template-columns:minmax(0,3fr) minmax(0,2fr);align-items:start">
        <div class="panel"><div class="panel-head"><h2>Where People Click</h2><span class="hm-legend"><i style="--h:.15"></i> few <i style="--h:.55"></i><i style="--h:1"></i> most</span></div>
            <div class="panel-body hm-wrap" id="heatmap">
                {!! $html !!}
            </div>
        </div>
        <div class="panel"><div class="panel-head"><h2>Clicks by Link</h2></div><div class="table-wrap"><table>
            <thead><tr><th>Link</th><th class="num">Clicks</th><th class="num">People</th><th class="num">%</th></tr></thead>
            <tbody>@foreach (collect($links)->sortByDesc(fn ($l, $k) => $counts[$k]->n ?? 0) as $k => $l)
                @php $n = $counts[$k]->n ?? 0; @endphp
                <tr data-hm-row="{{ $k }}"><td class="wrap" style="min-width:0"><b>{{ $l['label'] }}</b>@if ($l['url'])<div class="help mono">{{ $l['url'] }}</div>@endif</td><td class="num">{{ number_format($n) }}</td><td class="num">{{ number_format($counts[$k]->people ?? 0) }}</td>
                    <td class="num"><span class="hm-bar"><span style="width:{{ $total ? round(100 * $n / $total) : 0 }}%"></span></span> {{ $total ? round(100 * $n / $total) : 0 }}%</td></tr>
            @endforeach</tbody>
        </table></div></div>
    </div>

    <script>
    (function () {
        var counts = @json($counts->map(fn ($c) => (int) $c->n)), max = {{ $max }}, total = {{ max(1, $total) }};
        document.querySelectorAll('#heatmap a[data-link]').forEach(function (a) {
            var n = counts[a.dataset.link] || 0, h = n / max;
            a.classList.add('hm-link'); a.style.setProperty('--h', h.toFixed(2));
            a.setAttribute('data-tip', n + ' ' + (n === 1 ? 'click' : 'clicks') + ' · ' + Math.round(100 * n / total) + '%');
            a.addEventListener('click', function (e) { e.preventDefault(); });
            var b = document.createElement('span'); b.className = 'hm-count'; b.textContent = n; a.appendChild(b);
        });
    })();
    </script>
@endsection
