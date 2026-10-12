{{-- Entry counts by model name, grouped like the original "Logs" pages. Click a model to filter. --}}
<div class="grid-3">
    @foreach ($counts as $group => $rows)
        <div class="panel"><div class="panel-head"><h3 style="margin:0">Logs - {{ $group }}</h3>
            <a class="help" href="{{ request()->fullUrlWithQuery(['hgroup' => $group, 'hmodel' => null]) }}{{ $anchor ?? '' }}">{{ $rows->sum('n') }}</a></div>
            <div class="table-wrap"><table class="hist">
                <tbody>@foreach ($rows as $r)
                    <tr @class(['on' => ($filters['hmodel'] ?? '') === $r->model])>
                        <td class="num" style="width:3em">{{ $r->n }}</td>
                        <td class="mono"><a href="{{ request()->fullUrlWithQuery(['hmodel' => $r->model, 'hgroup' => null]) }}{{ $anchor ?? '' }}">{{ $r->model }}</a></td>
                        <td class="help">{{ \Illuminate\Support\Carbon::parse($r->last_at)->format('Y-m-d') }}</td>
                    </tr>
                @endforeach</tbody>
            </table></div>
        </div>
    @endforeach
</div>
