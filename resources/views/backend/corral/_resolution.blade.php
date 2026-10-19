{{-- Resolution of one call, email, text or chat: the current status and chips to change it. $type = call | contact, $id, $value --}}
<form method="post" action="{{ $type === 'chat' ? route('deputy.conversations.resolution', $id) : route('corral.contacts.resolution', [$type, $id]) }}" class="resolution-form">@csrf
    <div class="chips small">@foreach (config('corral.resolutions') as $r => $tone)<label><input type="radio" name="resolution" value="{{ $r }}" @checked($value === $r) onchange="this.form.requestSubmit()"><span>{{ $r }}</span></label>@endforeach</div>
    @if (! empty($by))<div class="help" style="margin-top:6px">Set by {{ $by }}@if (! empty($at)), {{ $at->diffForHumans() }}@endif</div>@endif
    <noscript><button class="btn sm">Save</button></noscript>
</form>
