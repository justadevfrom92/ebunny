@extends('backend.layouts.app')

@section('crumb', 'Account '.$c->account.' › Spend Stars')

@section('content')
    @include('backend.partials.page-head', [
        'title' => 'Spend Stars',
        'sub' => e($c->name).' · Account <span class="mono">'.e($c->account).'</span> · <b>'.number_format($c->stars).'</b> stars available',
        'actions' => '<a class="btn ghost" href="'.route('corral.customers.show', $c).'#stars">Back to account</a>',
    ])

    @error('offer')<div class="alert bad">{{ $message }}</div>@enderror

    <div class="panel"><div class="panel-head"><h2>Rangler Rewards</h2></div><div class="panel-body">
        <div class="offers">
            @foreach ($offers as $o)
                @php [$i, $cost, $name, $desc] = [$o->id, $o->stars, $o->name, $o->description]; @endphp
                <form method="post" action="{{ route('corral.customers.stars.redeem', $c) }}" class="offer {{ $cost > $c->stars ? 'locked' : '' }}"
                      data-confirm="Redeem {{ $name }}?|{{ $cost }} stars will be taken from the account.|Redeem">@csrf
                    <input type="hidden" name="offer" value="{{ $i }}">
                    <b class="stars">{{ $cost ? number_format($cost).' ★' : 'Free' }}</b>
                    <b>{{ $name }}</b>
                    <span class="help">{{ $desc }}</span>
                    <button class="btn sm" @disabled($cost > $c->stars)>{{ $cost > $c->stars ? 'Needs '.number_format($cost - $c->stars).' more' : 'Redeem' }}</button>
                </form>
            @endforeach
        </div>
    </div></div>

    <div class="panel"><div class="panel-head"><h2>Star History</h2></div><div class="table-wrap"><table>
        <thead><tr><th>Date</th><th>Reason</th><th class="num">Stars</th></tr></thead>
        <tbody>@forelse ($c->starEntries()->latest()->get() as $s)
            <tr><td>{{ $s->created_at->format('n/j/Y g:i A') }}</td><td>{{ $s->reason }}</td><td class="num">{{ $s->stars >= 0 ? '+' : '' }}{{ $s->stars }}</td></tr>
        @empty <tr><td colspan="3" class="empty">No stars earned yet.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
