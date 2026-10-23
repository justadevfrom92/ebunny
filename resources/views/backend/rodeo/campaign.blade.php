@extends('backend.layouts.app')

@section('crumb', $campaign->exists ? $campaign->name : 'New Campaign')

@section('content')
    @php
        $a = old('audience', $campaign->audience ?? []);
        $scheduled = $campaign->status === 'scheduled';
        $v = fn ($k, $d = null) => old($k, $campaign->{$k} ?? $d);
        $products = array_keys(config('corral.products'));
    @endphp
    @include('backend.partials.page-head', ['title' => $campaign->exists ? $campaign->name : 'New Campaign',
        'sub' => $scheduled ? 'Scheduled to send '.$campaign->scheduled_at->format('l, M j \a\t g:i A').'.' : ($campaign->exists ? 'Draft. Check the audience and message, then send it or schedule it.' : 'Set the goal, who gets it and what they get. Save to see the preview.'),
        'actions' => $campaign->exists ? '<form method="post" action="'.route('rodeo.campaigns.duplicate', $campaign).'" class="inline">'.csrf_field().'<button class="btn ghost">Duplicate</button></form>' : null])
    @if (! $ready)<div class="banner-note" style="margin-bottom:14px">{{ $campaign->channel === 'SMS' ? 'Twilio' : 'Salesforce' }} isn't set up in .env, so sending logs each message without delivering it (Sheriff → APIs).</div>@endif

    <div class="camp-grid">
        <form method="post" id="camp-form" action="{{ $campaign->exists ? route('rodeo.campaigns.update', $campaign) : route('rodeo.campaigns.store') }}" class="camp-steps">@csrf @if ($campaign->exists) @method('put') @endif
            <section class="panel"><div class="panel-head"><h2>1. Goal</h2><span class="muted">what counts as success</span></div><div class="panel-body">
                <div class="field"><label for="name">Name</label><input id="name" name="name" required maxlength="120" value="{{ $v('name') }}" placeholder="Fall renewal reminder"></div>
                <div class="chips" role="radiogroup" aria-label="Goal">@foreach (\App\Models\Campaign::GOALS as $k => [$label, $conv])
                    <label><input type="radio" name="goal" value="{{ $k }}" @checked($v('goal', 'renewal') === $k)><span><b>{{ $label }}</b><small>{{ $conv ? 'Converted: '.$conv : 'No conversion tracking' }}</small></span></label>
                @endforeach</div>
                <div class="field goal-product" @if ($v('goal') !== 'product') hidden @endif><label>Product it promotes</label>
                    <div class="chips small">@foreach ($products as $p)<label><input type="radio" name="product" value="{{ $p }}" @checked($v('product') === $p)><span>{{ $p }}</span></label>@endforeach</div></div>
                <div class="field"><label for="description">Notes</label><input id="description" name="description" maxlength="500" value="{{ $v('description') }}" placeholder="Why we're sending it, offer details…"></div>
            </div></section>

            <section class="panel"><div class="panel-head"><h2>2. Audience</h2><span class="muted">opted-in customers only</span></div><div class="panel-body">
                <div class="field"><label>Account status</label><div class="chips small">@foreach (array_keys(config('admin.customer_statuses')) as $s)<label><input type="checkbox" name="audience[statuses][]" value="{{ $s }}" @checked(in_array($s, $a['statuses'] ?? [], true))><span>{{ $s }}</span></label>@endforeach</div></div>
                <div class="field"><label>Customer type</label><div class="chips small">@foreach (['' => 'Any', 'Residential' => 'Residential', 'Small Business' => 'Small Business'] as $k => $l)<label><input type="radio" name="audience[type]" value="{{ $k }}" @checked(($a['type'] ?? '') === $k)><span>{{ $l }}</span></label>@endforeach</div></div>
                <div class="field"><label>Utility area</label><div class="chips small">@foreach ($markets as $m)<label><input type="checkbox" name="audience[market_ids][]" value="{{ $m->id }}" @checked(in_array((string) $m->id, array_map('strval', $a['market_ids'] ?? []), true))><span>{{ $m->short }}</span></label>@endforeach</div><small class="help">None picked = every area.</small></div>
                <div class="grid-2">
                    <div class="field"><label>AutoPay</label><div class="chips small">@foreach (['' => 'Any', 'yes' => 'On', 'no' => 'Off'] as $k => $l)<label><input type="radio" name="audience[autopay]" value="{{ $k }}" @checked(($a['autopay'] ?? '') === $k)><span>{{ $l }}</span></label>@endforeach</div></div>
                    <div class="field"><label>Balance</label><div class="chips small">@foreach (['' => 'Any', 'owes' => 'Owes money', 'paid' => 'Paid up'] as $k => $l)<label><input type="radio" name="audience[balance]" value="{{ $k }}" @checked(($a['balance'] ?? '') === $k)><span>{{ $l }}</span></label>@endforeach</div></div>
                </div>
                <details class="field" @if (! empty($a['product']) || ! empty($a['without_product'])) open @endif><summary>Products they have or don't have</summary>
                    <div class="grid-2" style="margin-top:8px">
                        <div><label>Has</label><div class="chips small"><label><input type="radio" name="audience[product]" value="" @checked(empty($a['product']))><span>Any</span></label>@foreach ($products as $p)<label><input type="radio" name="audience[product]" value="{{ $p }}" @checked(($a['product'] ?? '') === $p)><span>{{ $p }}</span></label>@endforeach</div></div>
                        <div><label>Doesn't have</label><div class="chips small"><label><input type="radio" name="audience[without_product]" value="" @checked(empty($a['without_product']))><span>—</span></label>@foreach ($products as $p)<label><input type="radio" name="audience[without_product]" value="{{ $p }}" @checked(($a['without_product'] ?? '') === $p)><span>{{ $p }}</span></label>@endforeach</div></div>
                    </div></details>
                <div class="grid-2">
                    <div class="field"><label for="a-ends">Contract ends within (days)</label><input id="a-ends" name="audience[contract_ends_within]" type="number" min="1" max="365" value="{{ $a['contract_ends_within'] ?? '' }}" placeholder="Any"></div>
                    <div class="field"><label for="cap_days">Skip anyone who got a campaign in the last (days)</label><input id="cap_days" name="cap_days" type="number" min="0" max="90" value="{{ $v('cap_days', 7) }}"><small class="help">0 = no limit</small></div>
                </div>
            </div></section>

            <section class="panel"><div class="panel-head"><h2>3. Message</h2></div><div class="panel-body">
                <div class="chips" role="radiogroup" aria-label="Channel">@foreach (['Email' => 'Email', 'SMS' => 'Text message'] as $k => $l)<label><input type="radio" name="channel" value="{{ $k }}" @checked($v('channel', 'Email') === $k)><span><b>{{ $l }}</b></span></label>@endforeach</div>
                <div class="ch-email field"><label>Email template</label>
                    <div class="table-wrap pick-list"><table class="table"><tbody>@foreach ($templates as $t)
                        <tr><td style="width:32px"><input type="radio" id="t{{ $t->id }}" name="email_template_id" value="{{ $t->id }}" @checked((string) $v('email_template_id') === (string) $t->id)></td><td><label for="t{{ $t->id }}"><b>{{ $t->name }}</b></label><br><span class="help">{{ $t->subject }}</span></td><td class="help">{{ $t->category?->name }}</td></tr>
                    @endforeach</tbody></table></div></div>
                <div class="ch-sms field"><label for="message">Text message</label><textarea id="message" name="message" maxlength="320" rows="3" placeholder="Hi @{{first_name}}, …">{{ $v('message') }}</textarea>
                    <small class="help"><span id="sms-len">0</span>/320 characters · <span id="sms-seg">1</span> text(s) · use @{{first_name}} and @{{promo_code}} · "Reply STOP to opt out" is added by Twilio</small></div>
            </div></section>

            <section class="panel"><div class="panel-head"><h2>4. Tracking</h2><span class="muted">so sign-ups from it are counted</span></div><div class="panel-body grid-2">
                <div class="field"><label>MSID (sales channel)</label><div class="chips small"><label><input type="radio" name="msid" value="" @checked(! $v('msid'))><span>None</span></label>@foreach ($channels as $ch)<label><input type="radio" name="msid" value="{{ $ch->msid }}" @checked($v('msid') === $ch->msid)><span>{{ $ch->msid }} · {{ $ch->name }}</span></label>@endforeach</div></div>
                <div class="field"><label>Promo code</label><div class="chips small"><label><input type="radio" name="promo_code" value="" @checked(! $v('promo_code'))><span>None</span></label>@foreach ($promos as $p)<label><input type="radio" name="promo_code" value="{{ $p->code }}" @checked($v('promo_code') === $p->code)><span>{{ $p->code }} · ${{ number_format($p->credit, 0) }}</span></label>@endforeach</div></div>
            </div></section>
            <div class="actions"><button class="btn cyan">{{ $campaign->exists ? 'Save Changes' : 'Save Draft' }}</button><a class="btn ghost" href="{{ route('rodeo.campaigns') }}">Back to Campaigns</a></div>
        </form>

        <aside class="camp-side">
            <section class="panel"><div class="panel-head"><h2>Audience</h2></div><div class="panel-body">
                <p class="camp-count"><strong id="aud-count">{{ $count !== null ? number_format($count) : '—' }}</strong> customers will get it</p>
                <dl class="kv camp-excl"><dt>Not opted in</dt><dd id="x-opt">{{ $excluded['not_opted_in'] ?? '—' }}</dd><dt>Suppressed (email)</dt><dd id="x-sup">{{ $excluded['suppressed'] ?? '—' }}</dd><dt>Contacted recently</dt><dd id="x-rec">{{ $excluded['recent'] ?? '—' }}</dd></dl>
                <p class="help" style="margin:6px 0 4px">For example:</p><ul class="camp-sample" id="aud-sample"></ul>
                <p class="help" style="margin:0">Suppressed and recently contacted customers are skipped when it sends.</p>
            </div></section>
            @if ($campaign->exists)
                <section class="panel"><div class="panel-head"><h2>Preview</h2><span class="muted">{{ $sample ? 'as '.$sample->first_name : 'no one matches' }}</span></div><div class="panel-body">
                    @if ($preview === null)<p class="muted">Pick an audience that matches someone to see the preview.</p>
                    @elseif ($campaign->channel === 'Email')<p class="help" style="margin-top:0"><b>Subject:</b> {{ $campaign->template?->renderSubject($sample) }}</p><iframe class="camp-prev" sandbox title="Email preview" srcdoc="{{ $preview }}"></iframe>
                    @else<p class="camp-sms">{{ $preview }}</p>@endif
                </div></section>
                <section class="panel"><div class="panel-head"><h2>Send</h2></div><div class="panel-body" style="display:grid;gap:12px">
                    @if ($scheduled)
                        <p style="margin:0">Sends <b>{{ $campaign->scheduled_at->format('D, M j g:i A') }}</b> ({{ $campaign->scheduled_at->diffForHumans() }}).</p>
                        <form method="post" action="{{ route('rodeo.campaigns.unschedule', $campaign) }}">@csrf<button class="btn ghost">Cancel Schedule</button></form>
                    @else
                        <form method="post" action="{{ route('rodeo.campaigns.schedule', $campaign) }}" class="camp-sched">@csrf
                            <div class="field"><label for="s-date">Date</label><input id="s-date" name="date" type="date" min="{{ today()->toDateString() }}" value="{{ old('date', today()->addDay()->toDateString()) }}"></div>
                            <div class="field"><label for="s-time">Time</label><input id="s-time" name="time" type="time" value="{{ old('time', '10:00') }}"></div>
                            <button class="btn">Schedule</button>
                        </form>
                        @error('time')<small class="help" style="color:var(--bad)">{{ $message }}</small>@enderror
                    @endif
                    @if ($count)
                        <form method="post" action="{{ route('rodeo.campaigns.send', $campaign) }}" data-confirm="Send this campaign now?|It goes to up to {{ number_format($count) }} customers right away.|Send Now">@csrf<button class="btn cyan" style="width:100%">Send Now to {{ number_format($count) }}</button></form>
                    @else<p class="muted" style="margin:0">No one matches this audience yet.</p>@endif
                    <p class="help" style="margin:0">Save your changes first: sending uses the saved version.</p>
                </div></section>
            @endif
        </aside>
    </div>

    <script>
    (function () {
        var f = document.getElementById('camp-form');
        function sync() {
            var goal = f.elements.goal ? f.elements.goal.value : '', ch = (f.querySelector('input[name=channel]:checked') || {}).value;
            f.querySelector('.goal-product').hidden = goal !== 'product';
            f.querySelectorAll('.ch-email').forEach(function (e) { e.hidden = ch !== 'Email'; });
            f.querySelectorAll('.ch-sms').forEach(function (e) { e.hidden = ch !== 'SMS'; });
            var m = f.message.value.length; document.getElementById('sms-len').textContent = m; document.getElementById('sms-seg').textContent = Math.max(1, Math.ceil(m / 160));
        }
        // Live audience count (nothing is saved)
        function count() {
            var q = new URLSearchParams(new FormData(f)); q.delete('_token'); q.delete('_method');
            fetch('{{ route('rodeo.campaigns.count') }}?' + q, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
                if (!j) return;
                document.getElementById('aud-count').textContent = j.count.toLocaleString();
                document.getElementById('x-opt').textContent = j.excluded.not_opted_in; document.getElementById('x-sup').textContent = j.excluded.suppressed; document.getElementById('x-rec').textContent = j.excluded.recent;
                var ul = document.getElementById('aud-sample'); ul.innerHTML = ''; j.sample.forEach(function (s) { var li = document.createElement('li'); li.textContent = s; ul.appendChild(li); });
                if (!j.sample.length) ul.innerHTML = '<li class="muted">No one matches.</li>';
            }).catch(function () {});
        }
        f.addEventListener('change', function (e) { sync(); if (/^audience|^channel$|^cap_days$/.test(e.target.name)) count(); });
        f.message.addEventListener('input', sync);
        sync(); count();
    })();
    </script>
@endsection
