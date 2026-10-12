@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Question Bank',
        'sub' => 'Ready-made questions, filed by category, to add to any survey from the survey builder. A survey keeps its own copy, so editing the bank never changes past surveys.',
        'actions' => '<a class="btn cyan" href="'.route('rodeo.surveys.bank.create', array_filter(['category' => $only])).'">New Question</a>'])

    <div>
        <div style="min-width:0">
            <div class="actions" style="margin-bottom:14px"><a class="btn sm {{ $only ? 'ghost' : '' }}" href="{{ route('rodeo.surveys.bank') }}">All</a>@foreach ($allCategories as $c)<a class="btn sm {{ (string) $only === (string) $c->id ? '' : 'ghost' }}" href="{{ route('rodeo.surveys.bank', ['category' => $c->id]) }}"><span class="sv-dot" style="--dot:{{ $c->color }};margin-right:6px"></span>{{ $c->name }}</a>@endforeach</div>
            @foreach ($categories as $cat)
                @continue($only && (string) $only !== (string) $cat->id)
                @include('backend.rodeo.surveys._bank-group', ['title' => $cat->name, 'desc' => $cat->description, 'dot' => $cat->color, 'items' => $cat->bankQuestions])
            @endforeach
            @if (! $only && $uncategorized->isNotEmpty())@include('backend.rodeo.surveys._bank-group', ['title' => 'Uncategorized', 'desc' => null, 'dot' => '#96999d', 'items' => $uncategorized])@endif
        </div>

    </div>
@endsection
