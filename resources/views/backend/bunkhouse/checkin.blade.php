@extends('backend.layouts.app')

@section('crumb', $c->user->name.' · '.$c->held_on->format('n/j/Y'))

@section('content')
    @include('backend.partials.page-head', ['title' => 'Check-in: '.$c->user->name, 'sub' => $c->held_on->format('l, F j, Y').' · with '.e($c->manager?->name ?? '—'),
        'actions' => '<a class="btn ghost" href="'.route('bunkhouse.people.show', $c->user).'">'.e($c->user->name).'</a><a class="btn cyan" href="'.route('bunkhouse.checkins.create', ['user' => $c->user_id]).'">Log Another</a>'])
    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Notes</h2></div><div class="panel-body"><dl class="kv">
            <dt>Feeling</dt><dd>@include('backend.partials.pill', ['text' => $c->moodLabel(), 'tone' => $c->mood >= 4 ? 'ok' : ($c->mood <= 2 ? 'bad' : '')])</dd>
            <dt>Talked About</dt><dd style="white-space:pre-line">{{ $c->topics }}</dd>
            <dt>Next Steps</dt><dd style="white-space:pre-line">{{ $c->action_items ?? '—' }}</dd>
        </dl></div></div>
        <div class="panel"><div class="panel-head"><h2>Earlier Check-ins</h2></div><div class="table-wrap"><table>
            <tbody>@forelse ($others as $o)<tr class="click" data-href="{{ route('bunkhouse.checkins.show', $o) }}"><td class="nowrap"><a href="{{ route('bunkhouse.checkins.show', $o) }}">{{ $o->held_on->format('n/j/Y') }}</a></td><td>{{ $o->moodLabel() }}</td><td class="wrap">{{ Str::limit($o->topics, 70) }}</td></tr>
            @empty <tr><td class="empty">This was the first one.</td></tr> @endforelse</tbody>
        </table></div></div>
    </div>
@endsection
