@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Exception Queues', 'sub' => 'Work items that need a person.'])
    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>Queue</th><th>What it holds</th><th class="num">Open</th><th></th></tr></thead>
        <tbody>@foreach ($queues as $key => [$name, $desc])
            @php $n = $counts[$key] ?? 0; @endphp
            <tr><td><b>{{ $name }}</b></td><td class="wrap muted">{{ $desc }}</td>
                <td class="num">@include('backend.partials.pill', ['text' => $n, 'tone' => $n === 0 ? 'ok' : ($n > 5 ? 'bad' : 'warn')])</td>
                <td><a class="btn sm ghost" href="{{ route('corral.queues.show', $key) }}">Open</a></td></tr>
        @endforeach</tbody>
    </table></div></div>
@endsection
