@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Models', 'sub' => 'Open-weight models: downloaded to your local model server, or hosted behind the open models API. Agents pick from these.',
        'actions' => '<a class="btn cyan" href="'.route('deputy.models.create').'">Add a Model</a>'])
    <div class="panel"><div class="table-wrap"><table class="table">
        <thead><tr><th>Model</th><th>Provider</th><th>Source</th><th class="num">Size</th><th class="num">Context</th><th class="num">Agents</th><th>Status</th><th></th></tr></thead>
        <tbody>@forelse ($models as $m)
            <tr><td>@include('backend.deputy._model', ['m' => $m])</td><td>{{ $m->providerLabel() }}</td>
                <td class="wrap">{{ $m->source ?? '—' }}@if ($m->quantization)<div class="help">{{ $m->quantization }}</div>@endif</td>
                <td class="num">{{ $m->size_gb ? number_format($m->size_gb, 1).' GB' : '—' }}</td>
                <td class="num">{{ $m->context_window ? number_format($m->context_window) : '—' }}</td>
                <td class="num">{{ $m->agents_count }}@if ($n = $usedAsFallback[$m->id] ?? 0)<div class="help">+{{ $n }} as fallback</div>@endif</td>
                <td>@include('backend.partials.pill', ['text' => ucfirst($m->status), 'tone' => ['available' => 'ok', 'downloading' => 'info', 'missing' => 'warn'][$m->status] ?? ''])@if ($m->checked_at)<div class="help">checked {{ $m->checked_at->diffForHumans() }}</div>@endif</td>
                <td class="actions"><form method="post" action="{{ route('deputy.models.check', $m) }}" class="inline">@csrf<button class="btn sm ghost">Check</button></form><a class="btn sm ghost" href="{{ route('deputy.models.edit', $m) }}">Edit</a></td></tr>
        @empty <tr><td colspan="8" class="empty">No models yet.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
