@extends('frontend.layouts.main')
@section('title', $m->template.' · Message Center')
@section('crumb')<a href="{{ route('myaccount.dashboard') }}">My Account</a> / <a href="{{ route('myaccount.messages') }}">Message Center</a>@endsection
@section('heading', 'My Account')
@section('no-banner', '1')
@section('main')
@php
    $when = $m->sent_at ?? $m->created_at;
    $go = fn ($a) => match ($a[1]) {
        'pdf' => $bill ? route('myaccount.bill.pdf', $bill) : null,
        'products' => route('myaccount.products').'#'.($a[2] ?? ''),
        default => route($a[1]),
    };
@endphp
<x-frontend.myaccount :c="$c" :title="$subject">
  <p class="msg-back"><a href="{{ route('myaccount.messages') }}">‹ All messages</a></p>
  <div class="msg-grid">
    <div class="msg-main">
      <section class="co-card">
        <p class="msg-tags"><span class="msg-kind">{{ $m->channel === 'SMS' ? 'Text message' : 'Email' }}</span><span class="msg-kind alt">{{ $info['label'] }}</span></p>
        <h3>What this is about</h3>
        <p>{{ $info['why'] }}</p>
        @php $actions = collect($info['actions'] ?? [])->map(fn ($a) => [$a[0], $go($a), $a[1] === 'pdf'])->filter(fn ($a) => $a[1]); @endphp
        @if ($actions->isNotEmpty())
          <div class="ma-actions msg-actions">@foreach ($actions as [$label, $url, $newTab])<a @class(['btn', 'btn-outline' => ! $loop->first]) href="{{ $url }}" @if ($newTab) target="_blank" rel="noopener" @endif>{{ $label }}</a>@endforeach</div>
        @endif
      </section>

      <section class="co-card">
        <h3>{{ $m->channel === 'SMS' ? 'The text' : 'The email' }}</h3>
        @if ($m->channel === 'SMS')
          <div class="msg-sms"><p @class(['bubble', 'mine' => $m->direction === 'in'])>{{ $m->body ?: $m->template }}</p><small>{{ $m->direction === 'in' ? 'You' : config('brand.name') }} · {{ $when->format('M j, Y g:i A') }}</small></div>
        @elseif ($html)
          <p class="msg-sub"><b>Subject:</b> {{ $subject }}<br><b>To:</b> {{ $c->email }}</p>
          <iframe class="msg-frame" title="The email as you received it" sandbox="allow-same-origin" srcdoc="{{ $html }}"
            onload="try { this.style.height = Math.max(120, this.contentDocument.documentElement.scrollHeight + 4) + 'px'; } catch (e) {}"></iframe>
        @else
          <p class="msg-sub"><b>Subject:</b> {{ $m->template }}</p>
          <p style="white-space:pre-line">{{ $m->body ?: 'This message had no text beyond its subject.' }}</p>
        @endif
      </section>
    </div>

    <aside class="msg-side">
      <section class="co-card">
        <h3>Details</h3>
        <dl class="msg-dl">
          <dt>Sent</dt><dd>{{ $when->format('l, M j, Y') }}<br><small>{{ $when->format('g:i A') }}</small></dd>
          <dt>{{ $m->channel === 'SMS' ? 'Phone' : 'To' }}</dt><dd>{{ $m->channel === 'SMS' ? ($m->phone ?: $c->phone) : $c->email }}</dd>
          <dt>Account</dt><dd>{{ $c->account }}</dd>
        </dl>
        <ol class="msg-steps">
          <li class="done">{{ $m->direction === 'in' ? 'Received by us' : 'Sent' }} <small>{{ $when->format('M j, g:i A') }}</small></li>
          @if ($m->channel === 'Email')
            <li @class(['done' => $m->opened_at])>Opened @if ($m->opened_at)<small>{{ $m->opened_at->format('M j, g:i A') }}</small>@else<small>not yet</small>@endif</li>
            <li @class(['done' => $m->clicked_at])>Link clicked @if ($m->clicked_at)<small>{{ $m->clicked_at->format('M j, g:i A') }}</small>@else<small>not yet</small>@endif</li>
          @endif
        </ol>
      </section>
      @if ($bill)
        <section class="co-card"><h3>The bill</h3>
          <dl class="msg-dl"><dt>Bill date</dt><dd>{{ $bill->billed_on->format('M j, Y') }}</dd><dt>Usage</dt><dd>{{ number_format($bill->kwh) }} kWh</dd>
            <dt>Amount</dt><dd><b>${{ number_format($bill->amount, 2) }}</b></dd><dt>Due</dt><dd>{{ $bill->due_on?->format('M j, Y') ?? '—' }}</dd><dt>Status</dt><dd>{{ $bill->paid_on ? 'Paid '.$bill->paid_on->format('M j') : 'Open' }}</dd></dl>
          <a class="pdf-link" href="{{ route('myaccount.bill.pdf', $bill) }}" target="_blank" rel="noopener">@include('frontend.myaccount._pdf-icon') View PDF</a>
        </section>
      @endif
      @if ($payment)
        <section class="co-card"><h3>The payment</h3>
          <dl class="msg-dl"><dt>Date</dt><dd>{{ $payment->paid_on?->format('M j, Y') }}</dd><dt>Amount</dt><dd><b>${{ number_format($payment->amount, 2) }}</b></dd>
            <dt>Method</dt><dd>{{ $payment->method }}</dd><dt>Status</dt><dd>{{ $payment->status }}</dd><dt>Confirmation</dt><dd>{{ $payment->confirmation ?? $payment->reference }}</dd></dl>
        </section>
      @endif
      <section class="co-card msg-help"><h3>Need help?</h3><p>Chat with us (bottom right) or call <a href="tel:{{ preg_replace('/[^\d+]/', '', config('brand.phone')) }}">{{ config('brand.phone') }}</a>.</p></section>
    </aside>
  </div>
  <nav class="msg-pager" aria-label="Messages">
    @if ($newer)<a href="{{ route('myaccount.message', $newer) }}">‹ Newer message</a>@else<span></span>@endif
    @if ($older)<a href="{{ route('myaccount.message', $older) }}">Older message ›</a>@endif
  </nav>
</x-frontend.myaccount>
@endsection
