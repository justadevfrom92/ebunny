@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Email Categories',
        'sub' => 'Groups email templates are filed under. The Templates list and the Emails overview are grouped by these, in this order.',
        'actions' => '<a class="btn cyan" href="'.route('rodeo.emails.categories.create').'">New Category</a>'])

    <div class="panel" style="margin-bottom:20px"><div class="table-wrap"><table class="table">
        <thead><tr><th class="num">Order</th><th>Category</th><th>Description</th><th>Templates</th><th></th></tr></thead>
        <tbody>@forelse ($categories as $c)
            <tr><td class="num">{{ $c->position }}</td>
                <td class="nowrap"><span class="sv-cat"><span class="sv-dot" style="--dot:{{ $c->color }}"></span>{{ $c->name }}</span></td>
                <td class="wrap">{{ $c->description }}</td>
                <td class="wrap">@forelse ($c->templates as $t)<a href="{{ route('rodeo.templates.edit', $t) }}">{{ $t->name }}</a>@if (! $loop->last), @endif @empty<span class="muted">None</span>@endforelse</td>
                <td class="actions"><a class="btn sm ghost" href="{{ route('rodeo.emails.categories.edit', $c) }}">Edit</a>
                    <form method="post" action="{{ route('rodeo.emails.categories.destroy', $c) }}" class="inline" data-confirm="Delete {{ $c->name }}?|Its {{ $c->templates_count }} {{ Str::plural('template', $c->templates_count) }} become uncategorized; nothing else changes.|Delete">@csrf @method('delete')<button class="btn sm ghost">Delete</button></form></td></tr>
        @empty <tr><td colspan="5" class="empty">No categories yet.</td></tr> @endforelse</tbody>
    </table></div></div>

    @if ($uncategorized->isNotEmpty())
        <div class="panel"><div class="panel-head"><h2>Uncategorized</h2><span class="muted">{{ $uncategorized->count() }}</span></div>
            <div class="panel-body">@foreach ($uncategorized as $t)<a href="{{ route('rodeo.templates.edit', $t) }}">{{ $t->name }}</a>@if (! $loop->last), @endif @endforeach</div></div>
    @endif
@endsection
