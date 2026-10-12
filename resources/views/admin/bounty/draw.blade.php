@extends('admin.layouts.app')

@section('crumb', 'Draw a Winner')

@section('content')
    @include('admin.partials.page-head', ['title' => 'Draw a Winner', 'sub' => 'The '.$month->format('F Y').' drawing. One entry is picked at random; the winner gets a note on their account and is saved to Bounty → Drawing Winners.',
        'actions' => '<a class="btn ghost" href="'.route('bounty.drawing').'">Back to Entries</a>'])

    @if ($winner)
        <div class="panel"><div class="panel-body">
            <p style="margin:0">{{ $month->format('F Y') }} already has a winner: account <b class="mono">{{ $winner->cells[2] }}</b> ({{ $winner->cells[1] }}, drawn {{ $winner->cells[3] ?? '' }}).</p>
        </div></div>
    @else
        <form method="post" action="{{ route('bounty.draw') }}" class="panel" data-confirm="Draw the {{ $month->format('F') }} winner?|One of {{ $count }} entries is picked at random. This can't be redone.|Draw Winner">@csrf
            <div class="panel-head"><h2>{{ $month->format('F Y') }} Drawing</h2></div>
            <div class="panel-body">
                <div class="grid-3 stat-row">
                    <div class="stat"><span>Entries</span><strong>{{ number_format($count) }}</strong></div>
                    <div class="stat"><span>Customers Entered</span><strong>{{ number_format($people) }}</strong></div>
                    <div class="stat"><span>Each Entry's Chance</span><strong>{{ $count ? '1 in '.number_format($count) : '—' }}</strong></div>
                </div>
                <div class="field"><label for="prize">Prize</label><input id="prize" name="prize" required maxlength="100" value="{{ old('prize', '$100 Bill Credit') }}"></div>
            </div>
            <div class="panel-body actions"><button class="btn cyan" @disabled(! $count)>Draw Winner</button>@unless ($count)<span class="muted">No entries this month yet.</span>@endunless</div>
        </form>
    @endif
@endsection
