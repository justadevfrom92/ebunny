@extends('frontend.layouts.main')
@section('title', $t->number())
@section('heading', 'My Account')
@section('main')
<x-frontend.myaccount :c="$c" :title="$t->subject">
  <div class="co-card">
    <p class="sp-meta"><b>{{ $t->number() }}</b> · {{ \App\Models\SupportTicket::TOPICS[$t->topic] ?? '' }} · opened {{ $t->created_at->format('M j, Y g:i A') }} ·
      <span class="sp-status sp-{{ $t->status }}">{{ $t->status === 'waiting' ? 'Waiting on you' : ($t->status === 'new' ? 'Received' : ucfirst($t->status)) }}</span></p>

    @if ($t->isDone())
      <div class="sp-rate">
        @if ($t->rating)
          <p><b>Thanks for your feedback.</b> You said we {{ $t->rating === 'good' ? 'helped 👍' : 'didn\'t help 👎' }}.@if ($t->rating_comment) “{{ $t->rating_comment }}”@endif</p>
        @else
          <form method="post" action="{{ route('myaccount.support.rate', $t->id) }}">@csrf
            <p><b>Did we help?</b></p>
            <div class="sp-topics"><label><input type="radio" name="rating" value="good" required><span>👍 Yes</span></label><label><input type="radio" name="rating" value="bad"><span>👎 No</span></label></div>
            <div class="field"><label for="sp-comment">Anything to add? (optional)</label><textarea id="sp-comment" name="comment" maxlength="1000" style="min-height:80px"></textarea></div>
            <button class="btn">Send Rating</button>
          </form>
        @endif
      </div>
    @endif

    <div class="sp-thread">
      @foreach ($messages as $m)
        @php $who = $m->author === 'customer' ? 'You' : ($m->user ? \Illuminate\Support\Str::before($m->user->name, ' ') : 'Support').' · '.config('brand.name'); @endphp
        @if ($m->via === 'phone')
          {{-- Calls show as a line; the call notes are the team's own --}}
          <p class="sp-call">📞 {{ $m->direction === 'in' ? 'You called us' : 'We called you' }}{{ $m->user ? ' · spoke with '.\Illuminate\Support\Str::before($m->user->name, ' ') : '' }} · {{ $m->durationLabel() }} min <small>{{ $m->created_at->format('M j, Y g:i A') }}</small></p>
        @else
          <div class="sp-msg sp-{{ $m->author === 'customer' ? 'you' : 'us' }}">
            <div class="sp-who"><b>{{ $who }}</b> @if ($m->via === 'email')<small>✉ by email</small> @endif<small>{{ $m->created_at->format('M j, Y g:i A') }}</small></div>
            <div>{!! nl2br(e($m->body)) !!}</div>
            @if ($m->attachments->isNotEmpty())
              <div class="sp-files">@foreach ($m->attachments as $a)<a href="{{ route('myaccount.support.file', [$t->id, $a->id]) }}">{{ $a->icon() }} {{ $a->name }} <small>{{ $a->sizeLabel() }}</small></a>@endforeach</div>
            @endif
          </div>
        @endif
      @endforeach
    </div>

    @if ($t->status !== 'closed')
      <form method="post" action="{{ route('myaccount.support.reply', $t->id) }}" enctype="multipart/form-data">@csrf
        <div class="field"><label for="sp-reply">{{ $t->isDone() ? 'Still need help? Reply and we\'ll reopen it' : 'Reply' }}</label><textarea id="sp-reply" name="body" required maxlength="5000"></textarea></div>
        <div class="field"><label for="sp-files">Attach files (optional)</label><input id="sp-files" type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.heic,.txt,.csv,.doc,.docx,.xls,.xlsx"></div>
        <div class="sp-actions"><a class="btn btn-outline btn-back" href="{{ route('myaccount.support') }}">Back</a><button class="btn">Send</button></div>
      </form>
    @else
      <p><small>This ticket is closed. <a href="{{ route('myaccount.support.create') }}">Open a new ticket</a> if you need more help.</small></p>
    @endif
  </div>
</x-frontend.myaccount>
@endsection
