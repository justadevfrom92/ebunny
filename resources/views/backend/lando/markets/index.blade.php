@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Markets', 'sub' => 'Utility service territories (TDSPs).',
        'actions' => '<a class="btn cyan" href="'.route('sheriff.markets.create').'">Add a Market</a>'])
    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th class="num">ID</th><th>Name</th><th>Short</th><th>Description</th><th>Region</th><th>Commodity</th><th>Utility Phone</th><th class="num">Zip Ranges</th><th>Status</th></tr></thead>
        <tbody>@foreach ($markets as $m)
            <tr class="click" data-href="{{ route('sheriff.markets.edit', $m) }}"><td class="num">{{ $m->id }}</td><td class="mono"><a href="{{ route('sheriff.markets.edit', $m) }}"><b>{{ $m->name }}</b></a></td><td>{{ $m->short }}</td><td>{{ $m->description }}</td><td>{{ $m->region }}</td>
                <td>{{ $m->commodity }}</td><td>{{ $m->phone }}</td><td class="num">{{ $m->zip_ranges_count }}</td>
                <td>@include('backend.partials.pill', ['text' => $m->status, 'tone' => $m->status === 'Active' ? 'ok' : ''])</td></tr>
        @endforeach</tbody>
    </table></div></div>
@endsection
