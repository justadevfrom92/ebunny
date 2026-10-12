@extends('backend.layouts.app')

@section('content')
    @php $by = collect($checks)->countBy('state'); @endphp
    @include('backend.partials.page-head', ['title' => 'Site Health',
        'sub' => 'Checked just now: '.($by['ok'] ?? 0).' OK, '.($by['warn'] ?? 0).' to look at, '.($by['bad'] ?? 0).' '.Str::plural('problem', $by['bad'] ?? 0).'.',
        'actions' => '<a class="btn ghost" href="'.route('lando.health').'">Check Again</a>'])
    <div class="panel" style="margin-bottom:20px">@include('backend.lando.site._checks', ['checks' => $checks])</div>
    <div class="panel"><div class="panel-head"><h2>About This Server</h2></div>
        <div class="table-wrap"><table><tbody>@foreach ($about as $k => $v)<tr><td style="width:220px">{{ $k }}</td><td class="mono">{{ $v }}</td></tr>@endforeach</tbody></table></div>
    </div>
@endsection
