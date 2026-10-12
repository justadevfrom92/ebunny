{{-- A model name with its provider, e.g. in tables. --}}
@if ($m)<span class="dp-model {{ $m->isLocal() ? 'local' : 'hosted' }}"><b>{{ $m->name }}</b><small class="mono">{{ $m->model_id }}</small></span>@else<span class="muted">—</span>@endif
