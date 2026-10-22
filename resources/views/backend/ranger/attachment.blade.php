@extends('backend.layouts.app')

@section('crumb', $a->name)

{{-- One ticket attachment: the file with a preview, who sent it and on which message. --}}
@section('content')
    @include('backend.partials.page-head', ['title' => $a->name, 'sub' => $a->icon().' '.e($a->sizeLabel()).' · '.e($a->mime).' · on <span class="mono">'.$t->number().'</span>',
        'actions' => '<a class="btn cyan" href="'.route('ranger.tickets.attachment.file', [$t, $a]).'">Download</a> <a class="btn ghost" href="'.route('ranger.tickets.show', $t).'">Back to '.$t->number().'</a>'])

    <div class="grid-2" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr);align-items:start">
        <div class="panel"><div class="panel-head"><h2>Preview</h2></div><div class="panel-body">
            @if ($a->isImage())<img class="att-preview" src="{{ route('ranger.tickets.attachment.file', [$t, $a, 'inline' => 1]) }}" alt="{{ $a->name }}">
            @elseif ($a->isPdf())<iframe class="att-frame" src="{{ route('ranger.tickets.attachment.file', [$t, $a, 'inline' => 1]) }}" title="{{ $a->name }}"></iframe>
            @elseif ($text !== null)<pre class="att-text">{{ $text }}</pre>
            @else<p class="muted">No preview for this kind of file. Download it to open it.</p>@endif
        </div></div>

        <div class="panel"><div class="panel-head"><h2>File</h2></div><div class="panel-body"><dl class="kv">
            <dt>Name</dt><dd>{{ $a->name }}</dd>
            <dt>Size</dt><dd>{{ $a->sizeLabel() }}</dd>
            <dt>Type</dt><dd>{{ $a->mime }}</dd>
            <dt>From</dt><dd>{{ $a->from_customer ? $t->name.' (customer)' : ($a->user?->name ?? 'Support') }}</dd>
            <dt>Sent</dt><dd>{{ $a->created_at->format('n/j/Y g:i A') }}@if ($a->message)<div class="help">with {{ $a->message->via === 'email' ? 'an email' : 'a My Account message' }}</div>@endif</dd>
            <dt>Ticket</dt><dd><a class="mono" href="{{ route('ranger.tickets.show', $t) }}">{{ $t->number() }}</a> {{ $t->subject }}</dd>
            @if ($t->customer)<dt>Account</dt><dd>@include('backend.partials.account-link', ['c' => $t->customer])</dd>@endif
        </dl></div>
            @if ($a->message)<div class="panel-body" style="border-top:1px solid var(--line)"><div class="help" style="margin-bottom:4px">The message it came with</div><div class="rt-body">{!! nl2br(e(\Illuminate\Support\Str::limit($a->message->body, 600))) !!}</div></div>@endif
        </div>
    </div>
@endsection
