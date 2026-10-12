@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Plan Groups', 'sub' => 'Groups control which plans each website section shows. "featured" is the home page; "resi" is the Plans page.',
        'actions' => '<a class="btn cyan" href="'.app_route('groups.create').'">Add a Group</a>'])
    <div class="grid-2">
        @foreach ($groups as $g)
            <div class="panel"><div class="panel-head"><h2>{{ $g->name }}</h2><span class="actions"><span class="mono muted">{{ $g->slug }}</span><a class="btn sm ghost" href="{{ app_route('groups.edit', $g) }}">Edit</a></span></div><div class="panel-body">
                <div class="actions">
                    @forelse ($g->plans as $p)
                        <form method="post" action="{{ app_route('groups.detach', [$g, $p]) }}" class="inline">@csrf @method('delete')
                            <span class="pill info">{{ $p->internal }} <button class="link-btn" aria-label="Remove {{ $p->internal }}">×</button></span>
                        </form>
                    @empty <span class="muted">No plans in this group.</span> @endforelse
                </div>
                <form method="post" action="{{ app_route('groups.attach', $g) }}" class="form-row" style="margin-top:12px">@csrf
                    <select name="plan_id" aria-label="Add a plan to {{ $g->name }}" data-autosubmit><option value="">Add a plan…</option>
                        @foreach ($plans->whereNotIn('id', $g->plans->pluck('id')) as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->internal }})</option>@endforeach
                    </select>
                </form>
            </div></div>
        @endforeach
    </div>
@endsection
