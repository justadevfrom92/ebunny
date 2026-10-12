@extends('admin.layouts.app')

@section('content')
    @include('admin.partials.page-head', ['title' => 'Markets', 'sub' => 'Utility service territories (TDSPs).',
        'actions' => '<a class="btn cyan" href="'.route('sheriff.markets.create').'">Add a Market</a>'])
    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th class="num">ID</th><th>Name</th><th>Short</th><th>Description</th><th>Region</th><th>Commodity</th><th>Utility Phone</th><th class="num">Zip Ranges</th><th>Status</th><th></th></tr></thead>
        <tbody>@foreach ($markets as $m)
            <tr><td class="num">{{ $m->id }}</td><td class="mono"><b>{{ $m->name }}</b></td><td>{{ $m->short }}</td><td>{{ $m->description }}</td><td>{{ $m->region }}</td>
                <td>{{ $m->commodity }}</td><td>{{ $m->phone }}</td><td class="num">{{ $m->zip_ranges_count }}</td>
                <td>@include('admin.partials.pill', ['text' => $m->status, 'tone' => $m->status === 'Active' ? 'ok' : ''])</td>
                <td><a class="btn sm ghost" href="{{ route('sheriff.markets.edit', $m) }}">Edit</a></td></tr>
        @endforeach</tbody>
    </table></div></div>
@endsection
