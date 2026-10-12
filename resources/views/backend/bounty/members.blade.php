@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Members', 'sub' => 'Look up a customer\'s stars, see their history and add or remove stars.'])
    <form method="get" class="panel"><div class="panel-body form-row"><input name="q" value="{{ $q }}" placeholder="Account, name or email" aria-label="Search members" style="flex:1"><button class="btn">Search</button></div></form>
    @if ($q !== '')
        <div class="panel"><div class="table-wrap"><table><tbody>
            @forelse ($results as $c)<tr class="click" data-href="{{ route('bounty.members', ['account' => $c->account, 'q' => $q]) }}"><td><a href="{{ route('bounty.members', ['account' => $c->account, 'q' => $q]) }}">{{ $c->account }}</a></td><td>{{ $c->name }}</td><td>{{ $c->status }}</td><td class="num">{{ number_format($c->stars) }} ★</td></tr>
            @empty <tr><td class="empty">No customers match.</td></tr> @endforelse
        </tbody></table></div></div>
    @endif
    @if ($member)
        <div class="grid-2" style="grid-template-columns:1fr 340px;align-items:start">
            <div class="panel"><div class="panel-head"><h2>{{ $member->name }} · {{ $member->account }}</h2><span><b>{{ number_format($member->stars) }}</b> stars</span></div><div class="table-wrap"><table>
                <thead><tr><th>Date</th><th>Reason</th><th class="num">Stars</th></tr></thead>
                <tbody>@forelse ($history as $s)<tr><td>{{ $s->created_at->format('n/j/Y') }}</td><td>{{ $s->reason }}</td><td class="num">{{ $s->stars >= 0 ? '+' : '' }}{{ $s->stars }}</td></tr>
                @empty <tr><td colspan="3" class="empty">No stars yet.</td></tr> @endforelse</tbody>
            </table></div></div>
            <form method="post" action="{{ route('bounty.adjust', $member) }}" class="panel">@csrf
                <div class="panel-head"><h2>Adjust Stars</h2></div>
                <div class="panel-body" style="display:flex;flex-direction:column;gap:12px">
                    <div class="field"><label for="a-stars">Stars (+ to add, − to remove)</label><input id="a-stars" name="stars" type="number" required></div>
                    <div class="field"><label for="a-reason">Reason</label><input id="a-reason" name="reason" required placeholder="e.g. Goodwill for outage"></div>
                    <button class="btn cyan">Save Adjustment</button>
                </div>
            </form>
        </div>
    @endif
@endsection
