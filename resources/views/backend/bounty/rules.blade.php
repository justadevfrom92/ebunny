@extends('backend.layouts.app')

@section('content')
    <form method="post" action="{{ route('bounty.rules.save') }}" style="display:flex;flex-direction:column;gap:20px">@csrf @method('put')
        @include('backend.partials.page-head', ['title' => 'Earning Rules', 'sub' => 'How customers earn stars. The "Rewards stars" job (Sheriff → Crons, nightly) awards them; each award is only given once.',
            'actions' => '<button class="btn cyan">Save Rules</button>'])
        <div class="panel"><div class="table-wrap"><table>
            <thead><tr><th>Rule</th><th>Name on the Star History</th><th class="num">Stars</th><th>Active</th></tr></thead>
            <tbody>@foreach ($rules as $r)
                <tr><td class="mono">{{ $r->key }}</td><td><input class="cell-input" name="r[{{ $r->id }}][label]" value="{{ $r->label }}" required aria-label="{{ $r->key }} label"></td>
                    <td class="num"><input type="number" step="0.01" min="0" name="r[{{ $r->id }}][stars]" value="{{ $r->stars }}" required aria-label="{{ $r->key }} stars" style="width:9ch"> {{ $r->per_dollar ? 'per $1' : 'each' }}</td>
                    <td><input type="hidden" name="r[{{ $r->id }}][active]" value="0"><label class="check"><input type="checkbox" name="r[{{ $r->id }}][active]" value="1" @checked($r->active)> Active</label></td></tr>
            @endforeach</tbody>
        </table></div></div>
    </form>
    <div class="panel"><div class="panel-body form-row" style="align-items:center">
        <span>Last award run: {{ $last ? $last->started_at->format('n/j/Y g:i A').' · '.$last->status.' — '.$last->message : 'never' }}</span>
        <form method="post" action="{{ route('bounty.award') }}" class="inline">@csrf<button class="btn">Award Stars Now</button></form>
    </div></div>
@endsection
