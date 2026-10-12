@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Survey Categories',
        'sub' => 'Topics that questions are filed under. Results and responses are grouped by these, in this order.',
        'actions' => '<a class="btn cyan" href="'.route('rodeo.surveys.categories.create').'">New Category</a>'])

    <div>
        <div class="panel" style="min-width:0"><div class="table-wrap"><table class="table">
            <thead><tr><th class="num">Order</th><th>Category</th><th>Description</th><th class="num">In the Bank</th><th class="num">In Surveys</th><th></th></tr></thead>
            <tbody>@forelse ($categories as $c)
                <tr><td class="num">{{ $c->position }}</td>
                    <td><span class="sv-cat"><span class="sv-dot" style="--dot:{{ $c->color }}"></span>{{ $c->name }}</span></td>
                    <td class="wrap">{{ $c->description }}</td>
                    <td class="num"><a href="{{ route('rodeo.surveys.bank', ['category' => $c->id]) }}">{{ $c->bank_questions_count }}</a></td>
                    <td class="num">{{ $c->questions_count }}</td>
                    <td class="actions"><a class="btn sm ghost" href="{{ route('rodeo.surveys.categories.edit', $c) }}">Edit</a>
                        <form method="post" action="{{ route('rodeo.surveys.categories.destroy', $c) }}" class="inline" data-confirm="Delete {{ $c->name }}?|Its {{ $c->bank_questions_count + $c->questions_count }} questions become uncategorized; nothing else changes.|Delete">@csrf @method('delete')<button class="btn sm ghost">Delete</button></form></td></tr>
            @empty <tr><td colspan="6" class="empty">No categories yet.</td></tr> @endforelse</tbody>
        </table></div></div>

    </div>
@endsection
