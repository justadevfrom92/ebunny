<div class="sg-chips" aria-label="Variables used">
    @forelse ($names as $n)
        <button type="button" class="sg-chip" data-jump="{{ $n }}" title="Show {{ $n }}"><i style="--sg:var({{ $n }})"></i><span class="mono">{{ $n }}</span></button>
    @empty
        <span class="muted">No variables</span>
    @endforelse
</div>
