{{-- A person's name linking to their Bunkhouse page, with title or company underneath --}}
@if ($p)<a href="{{ route('bunkhouse.people.show', $p) }}"><b>{{ $p->name }}</b></a>@if (! ($short ?? false))<div class="help">{{ $p->company ? $p->company.' · 3rd party' : ($p->title ?? $p->department) }}</div>@endif @else — @endif
