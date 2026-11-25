@extends('backend.layouts.app')

@section('crumb', 'My Team')

{{-- Who you report to, who works alongside you and who reports to you: how to reach them and whether they're in --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'My Team', 'sub' => e($u->department ?? $u->company ?? 'No department').' · signed in now = active in the last 15 minutes'])

    @foreach ([[$u->company ? 'Our Contact' : 'My Manager', $u->manager ? collect([$u->manager]) : collect(), 'No manager set. Ask an administrator to add one in Sheriff.'],
              ['Works With Me', $peers, 'No one else reports to your manager.'],
              ['Reports to Me', $u->reports->where('active', true)->sortBy('name'), 'No one reports to you.']] as [$title, $people, $none])
        <div class="panel"><div class="panel-head"><h2>{{ $title }}</h2><span class="muted">{{ $people->count() }}</span></div><div class="table-wrap"><table data-page="0">
            <thead><tr><th>Name</th><th>Title</th><th>Phone Ext.</th><th>Email</th><th>Status</th></tr></thead>
            <tbody>@forelse ($people as $p)
                <tr><td><b>{{ $p->name }}</b><div class="help">{{ $p->role?->name }}</div></td><td>{{ $p->title ?? '—' }}</td><td class="mono">{{ $p->phone_ext ?? '—' }}</td>
                    <td><a href="mailto:{{ $p->email }}">{{ $p->email }}</a></td>
                    <td class="nowrap">@include('backend.partials.pill', ['text' => $p->availabilityLabel(), 'tone' => \App\Models\User::AVAILABILITY[$p->isOut() ? 'out' : ($p->availability === 'busy' ? 'busy' : 'available')][1]])
                        @if (isset($online[$p->id])) <span class="pill info">Signed in now</span>@endif
                        @if ($p->isOut() && $p->out_note)<div class="help">{{ $p->out_note }}</div>@endif</td></tr>
            @empty <tr><td colspan="5" class="empty">{{ $none }}</td></tr> @endforelse</tbody>
        </table></div></div>
    @endforeach
@endsection
