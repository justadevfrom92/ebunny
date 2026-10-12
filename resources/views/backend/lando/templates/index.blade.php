@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Page Templates', 'sub' => 'Layouts that pages are built on.',
        'actions' => '<a class="btn cyan" href="'.route('lando.templates.create').'">Add a Template</a>'])
    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>Template</th><th>Description</th><th>Layout File</th><th class="num">Pages</th><th></th></tr></thead>
        <tbody>@foreach ($templates as $t)
            <tr><td class="mono"><b>{{ $t->name }}</b></td><td class="wrap">{{ $t->description }}</td><td class="mono help">{{ $t->file ?? '—' }}</td><td class="num">{{ $t->pages_count }}</td>
                <td><a class="btn sm ghost" href="{{ route('lando.templates.edit', $t) }}">Edit</a></td></tr>
        @endforeach</tbody>
    </table></div></div>
@endsection
