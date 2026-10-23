{{-- Resolution of one call, email, text or chat: the current status, changed from a dropdown. $type = call | contact, $id, $value --}}
<form method="post" action="{{ $type === 'chat' ? route('deputy.conversations.resolution', $id) : route('corral.contacts.resolution', [$type, $id]) }}" class="resolution-form">@csrf
    <select name="resolution" aria-label="Resolution" onchange="this.form.requestSubmit()">@if (! $value)<option value="">Pick a resolution</option>@endif @foreach (config('corral.resolutions') as $r => $tone)<option @selected($value === $r)>{{ $r }}</option>@endforeach</select>
    @if (! empty($by))<div class="help" style="margin-top:6px">Set by {{ $by }}@if (! empty($at)), {{ $at->diffForHumans() }}@endif</div>@endif
    <noscript><button class="btn sm">Save</button></noscript>
</form>
