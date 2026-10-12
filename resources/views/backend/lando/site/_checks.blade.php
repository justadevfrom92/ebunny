{{-- Site health checks, grouped. $checks from DashboardController::checks(); $compact hides the "what to do" column. --}}
<div class="table-wrap"><table class="table health">
    <tbody>@foreach (collect($checks)->groupBy('group') as $group => $list)
        <tr class="health-group"><th colspan="{{ ($compact ?? false) ? 3 : 4 }}">{{ $group }}</th></tr>
        @foreach ($list as $c)
            <tr><td style="width:34px"><span class="health-dot {{ $c['state'] }}" title="{{ ['ok' => 'OK', 'warn' => 'Needs a look', 'bad' => 'Problem'][$c['state']] }}"></span></td>
                <td class="nowrap"><b>{{ $c['name'] }}</b></td>
                <td class="wrap">{{ $c['found'] }}@if (($compact ?? false) && $c['fix'])<div class="muted" style="font-size:.8rem">{{ $c['fix'] }}</div>@endif</td>
                @unless ($compact ?? false)<td class="wrap muted">{{ $c['fix'] }}</td>@endunless</tr>
        @endforeach
    @endforeach</tbody>
</table></div>
