@extends('backend.layouts.app')

@section('crumb', $template->name.' · Send Test')

@section('head')
<style>
    .tt-grid { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); gap: 20px; align-items: start; }
    .tt-col { display: grid; gap: 20px; min-width: 0; }
    .tt-col > .panel { min-width: 0; }
    .tt-col.tt-sticky { position: sticky; top: 72px; }
    .tt-step { display: inline-grid; place-items: center; width: 24px; height: 24px; border-radius: 50%; background: var(--navy); color: #fff; font-size: .8rem; margin-right: 8px; }
    .tt-modes { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
    .tt-mode { position: relative; border: 1px solid var(--line); border-radius: 8px; padding: 10px 12px; cursor: pointer; background: #fff; }
    .tt-mode input { position: absolute; opacity: 0; pointer-events: none; }
    .tt-mode b { display: block; color: var(--navy); }
    .tt-mode small { color: var(--muted); }
    .tt-mode:has(input:checked) { border-color: var(--cyan); box-shadow: inset 0 0 0 1px var(--cyan); background: var(--info-bg); }
    .tt-mode:has(input:focus-visible) { outline: 2px solid var(--cyan); outline-offset: 2px; }
    .tt-pane[hidden] { display: none; }
    .tt-list { max-height: 260px; overflow: auto; border-top: 1px solid var(--line); }
    .tt-summary { display: flex; flex-wrap: wrap; gap: 10px 18px; align-items: center; justify-content: space-between; }
    .tt-meta { display: grid; grid-template-columns: 70px 1fr; gap: 6px 10px; font-size: .9rem; padding: 12px 18px; border-bottom: 1px solid var(--line); }
    .tt-meta span { color: var(--muted); }
    .tt-frame-wrap { background: var(--row); padding: 16px; display: flex; justify-content: center; }
    .tt-frame { width: 100%; height: 520px; border: 1px solid var(--line); border-radius: 6px; background: #fff; transition: width .2s; }
    .tt-frame.phone { width: 375px; }
    .tt-seg { display: inline-flex; border: 1px solid var(--line); border-radius: 6px; overflow: hidden; }
    .tt-seg button { border: 0; background: #fff; padding: 5px 10px; cursor: pointer; color: var(--muted); }
    .tt-seg button.on { background: var(--navy); color: #fff; }
    .tt-status { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .tt-recips { margin: 0; padding: 0; list-style: none; }
    .tt-recips li { padding: 2px 0; }
    @media (max-width: 1100px) { .tt-grid { grid-template-columns: 1fr; } .tt-col.tt-sticky { position: static; } .tt-modes { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 560px) { .tt-modes, .tt-status { grid-template-columns: 1fr; } .tt-frame.phone { width: 100%; } }
</style>
@endsection

@section('content')
    @php
        $max = \App\Http\Controllers\Backend\Rodeo\TemplateTestController::MAX;
        $modes = [
            'me' => ['Just me', request()->user()->email],
            'emails' => ['Email addresses', 'type one or more'],
            'accounts' => ['Account numbers', 'their own fill-ins'],
            'bookmarks' => ['My bookmarks', $bookmarks->count().' with an email'],
            'team' => ['An admin team', 'everyone in a role'],
            'category' => ['Customer category', 'status, type, market'],
        ];
        $mode = old('mode', 'me');
    @endphp
    @include('backend.partials.page-head', ['title' => 'Send Test: '.$template->name,
        'sub' => 'Sends this template, as saved, to up to '.$max.' people. The subject gets a test prefix and the email opens with a banner saying who sent the test.',
        'actions' => '<a class="btn ghost" href="'.route('rodeo.templates.edit', $template).'">Edit Template</a>'])

    @if ($r = session('test_result'))<div class="flash {{ $r['ok'] ? 'ok' : 'bad' }}" role="status" style="margin-bottom:16px">{{ $r['message'] }}</div>@endif

    <div class="tt-status" style="margin-bottom:20px">
        <div class="stat"><span>Delivery</span><strong style="font-size:1.1rem">{{ $delivers ? 'Real email' : 'Mail log only' }}</strong><small>MAIL_MAILER = {{ config('mail.default') }}{{ $delivers ? '' : ' · set smtp in .env to deliver' }}</small></div>
        <div class="stat"><span>From</span><strong style="font-size:1.1rem">{{ config('mail.from.name') }}</strong><small>{{ config('mail.from.address') }}</small></div>
        <div class="stat"><span>Tests of this template</span><strong style="font-size:1.1rem">{{ $counts->sum() }}</strong><small>@forelse ($counts as $s => $n){{ $n }} {{ ['sent' => 'sent', 'logged' => 'logged', 'failed' => 'failed', 'partial' => 'partly sent'][$s] ?? $s }}@if (! $loop->last) · @endif @empty none yet @endforelse</small></div>
    </div>

    <form method="post" action="{{ route('rodeo.templates.test.send', $template) }}" id="tt-form" data-confirm="Send this test?|It goes out now, marked as a test.|Send Test">@csrf
    <div class="tt-grid">
        <div class="tt-col">
            {{-- 1. Who --}}
            <div class="panel">
                <div class="panel-head"><h2><span class="tt-step">1</span>Who gets it</h2></div>
                <div class="panel-body" style="display:grid;gap:16px">
                    <div class="tt-modes" role="radiogroup" aria-label="Send to">
                        @foreach ($modes as $key => [$label, $hint])
                            <label class="tt-mode"><input type="radio" name="mode" value="{{ $key }}" @checked($mode === $key)><b>{{ $label }}</b><small>{{ $hint }}</small></label>
                        @endforeach
                    </div>

                    <div class="tt-pane" data-pane-for="me"><p class="muted" style="margin:0">Goes to <b>{{ request()->user()->email }}</b>, filled in with the sample account below.</p></div>
                    <div class="tt-pane form-grid" data-pane-for="emails" hidden>
                        <label for="t-emails">Addresses</label><textarea id="t-emails" name="emails" placeholder="name@example.com, other@example.com" style="min-height:80px;font-family:inherit">{{ old('emails') }}</textarea>
                        <span></span><p class="help" style="margin:0">Separate with commas, spaces or new lines. Filled in with the sample account below.</p>
                    </div>
                    <div class="tt-pane form-grid" data-pane-for="accounts" hidden>
                        <label for="t-accounts">Accounts</label><textarea id="t-accounts" name="accounts" placeholder="1219000000, 1219000001" style="min-height:80px;font-family:inherit">{{ old('accounts') }}</textarea>
                        <span></span><p class="help" style="margin:0">Each account gets it at its email on file, with its own name, balance and plan. Accounts with no email are skipped.</p>
                    </div>
                    <div class="tt-pane" data-pane-for="bookmarks" hidden>
                        @if ($bookmarks->isEmpty())<p class="muted" style="margin:0">You have no bookmarked accounts with an email address. Bookmark accounts in Corral to test with them here.</p>
                        @else<p style="margin:0 0 6px">Your Corral bookmarks with an email address:</p><ul class="tt-recips">@foreach ($bookmarks as $b)<li>{{ $b->name }} @include('backend.partials.account-link', ['c' => $b]) · {{ $b->email }}</li>@endforeach</ul>@endif
                    </div>
                    <div class="tt-pane form-grid" data-pane-for="team" hidden>
                        <label for="t-role">Team</label><select id="t-role" name="role_id">@foreach ($roles as $role)<option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>{{ $role->name }} ({{ $role->users_count }} {{ Str::plural('person', $role->users_count) }})</option>@endforeach</select>
                    </div>
                    <div class="tt-pane form-grid" data-pane-for="category" hidden>
                        <label>Account status</label>@include('backend.partials.multiselect', ['id' => 't-status', 'name' => 'status[]', 'label' => 'Account status', 'options' => array_keys(config('admin.customer_statuses')), 'selected' => old('status', []), 'placeholder' => 'Any status'])
                        <label for="t-type">Customer type</label><select id="t-type" name="type"><option value="">Any</option>@foreach (['Residential', 'Small Business'] as $t)<option @selected(old('type') === $t)>{{ $t }}</option>@endforeach</select>
                        <label for="t-market">Market</label><select id="t-market" name="market_id"><option value="">Any</option>@foreach ($markets as $m)<option value="{{ $m->id }}" @selected(old('market_id') == $m->id)>{{ $m->name }}</option>@endforeach</select>
                        <label for="t-limit">How many</label><input id="t-limit" name="limit" type="number" min="1" max="{{ $max }}" value="{{ old('limit', 5) }}" style="max-width:8ch">
                        <span></span><p class="help" style="margin:0">These are real customers. Each gets their own fill-ins.</p>
                    </div>

                    <div class="tt-summary"><span class="muted" id="tt-count">Check who this will go to before sending.</span><button type="button" class="btn sm ghost" id="tt-check">Check Recipients</button></div>
                </div>
                <div class="tt-list" id="tt-list" hidden><div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>Email</th><th>Account</th><th>Fill-ins From</th></tr></thead><tbody></tbody></table></div></div>
            </div>

            {{-- 2. Options --}}
            <div class="panel">
                <div class="panel-head"><h2><span class="tt-step">2</span>Options</h2></div>
                <div class="panel-body form-grid">
                    <label for="t-sample">Sample account</label><div><input id="t-sample" name="sample" value="{{ old('sample', $sample?->account) }}" inputmode="numeric" style="max-width:16ch"> <span class="muted" id="tt-sample-name">{{ $sample?->name }}</span></div>
                    <span></span><p class="help" style="margin:0">Fills in the name, balance and plan for people who aren't customers (Just me, typed addresses, admin teams), and for the preview.</p>
                    <label for="t-prefix">Subject prefix</label><input id="t-prefix" name="prefix" value="{{ old('prefix', '[TEST]') }}" maxlength="30" style="max-width:20ch">
                    <label for="t-note">Note in the banner</label><textarea id="t-note" name="note" maxlength="500" placeholder="e.g. Checking the new logo and links" style="min-height:60px;font-family:inherit">{{ old('note') }}</textarea>
                    <span></span><div><input type="hidden" name="log_contact" value="0"><label class="check"><input type="checkbox" name="log_contact" value="1" @checked(old('log_contact', '1') === '1')> Add the test to each customer's Contact Log</label></div>
                </div>
            </div>

            {{-- 3. Send --}}
            <div class="panel">
                <div class="panel-head"><h2><span class="tt-step">3</span>Send</h2></div>
                <div class="panel-body tt-summary">
                    <p style="margin:0">{{ $delivers ? 'Sends real email now.' : 'Written to the mail log (not delivered) until MAIL_MAILER is set.' }} Up to {{ $max }} people.</p>
                    <button class="btn cyan">Send Test</button>
                </div>
            </div>
        </div>

        {{-- Preview --}}
        <div class="tt-col tt-sticky">
            <div class="panel">
                <div class="panel-head"><h2>Preview</h2><div class="tt-seg" role="group" aria-label="Preview width"><button type="button" class="on" data-width="desktop">Desktop</button><button type="button" data-width="phone">Phone</button></div></div>
                <div class="tt-meta"><span>From</span><div>{{ config('mail.from.name') }} &lt;{{ config('mail.from.address') }}&gt;</div><span>Subject</span><div><b id="tt-subject">…</b></div><span>As</span><div id="tt-as">…</div></div>
                <div class="tt-frame-wrap"><iframe class="tt-frame" id="tt-frame" title="Email preview" sandbox=""></iframe></div>
            </div>
        </div>
    </div>
    </form>

    {{-- History --}}
    <div class="panel" id="history" style="margin-top:20px">
        <div class="panel-head"><h2>Test History</h2>
            <div class="actions">@foreach (['' => 'All', 'sent' => 'Sent', 'logged' => 'Logged only', 'failed' => 'Failed', 'partial' => 'Partly sent'] as $v => $l)<a class="btn sm {{ (string) $statusFilter === $v ? '' : 'ghost' }}" href="{{ route('rodeo.templates.test', [$template, 'result' => $v ?: null]) }}#history">{{ $l }}</a>@endforeach</div></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>ID</th><th>When</th><th>By</th><th>Sent To</th><th>Subject &amp; Note</th><th>Recipients</th><th>Result</th><th></th></tr></thead>
            <tbody>@forelse ($tests as $t)
                @php $u = \App\Support\Records::url('email_test', $t->id); @endphp<tr><td class="nowrap"><a href="{{ $u }}"><b>#{{ $t->id }}</b></a></td><td class="nowrap">{{ $t->created_at->format('n/j/Y g:i A') }}</td><td>{{ $t->user?->name }}</td>
                    <td>{{ \App\Http\Controllers\Backend\Rodeo\TemplateTestController::MODES[$t->mode] ?? $t->mode }}@if ($t->target)<div class="muted">{{ Str::limit($t->target, 70) }}</div>@endif</td>
                    <td>{{ $t->subject ?? '—' }}@if ($t->note)<div class="muted">“{{ Str::limit($t->note, 80) }}”</div>@endif</td>
                    <td><details><summary>{{ count($t->recipients) }} {{ Str::plural('person', count($t->recipients)) }}</summary><ul class="tt-recips" style="margin-top:6px">@foreach ($t->recipients as $r)
                        <li>{{ $r['email'] }}@isset($r['account']) <span class="mono muted">{{ $r['account'] }}</span>@endisset
                            @include('backend.partials.pill', ['text' => ['sent' => 'Sent', 'logged' => 'Logged', 'failed' => 'Failed', 'suppressed' => 'Suppressed'][$r['status'] ?? ''] ?? '—', 'tone' => ['sent' => 'ok', 'failed' => 'bad'][$r['status'] ?? ''] ?? ''])
                            @isset($r['error'])<span class="muted">{{ $r['error'] }}</span>@endisset</li>
                    @endforeach</ul></details></td>
                    <td>@include('backend.partials.pill', ['text' => ['sent' => 'Sent', 'logged' => 'Logged only', 'failed' => 'Failed', 'partial' => 'Partly sent', 'suppressed' => 'Suppressed'][$t->status] ?? $t->status, 'tone' => ['sent' => 'ok', 'failed' => 'bad', 'partial' => 'warn'][$t->status] ?? ''])</td>
                    <td class="actions">@if ($t->params)<form method="post" action="{{ route('rodeo.templates.test.again', [$template, $t]) }}" class="inline" data-confirm="Send this test again?|Same people, same options.|Send Again">@csrf<button class="btn sm ghost">Send Again</button></form>@endif</td></tr>
            @empty <tr><td colspan="8" class="empty">No tests {{ $statusFilter ? 'with that result' : 'yet' }}.</td></tr> @endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $tests])
    </div>

    <script>
    (function () {
        var form = document.getElementById('tt-form');
        var urls = { recipients: @json(route('rodeo.templates.test.recipients', $template)), preview: @json(route('rodeo.templates.test.preview', $template)) };
        var radios = [].slice.call(form.querySelectorAll('input[name=mode]'));
        var list = document.getElementById('tt-list'), count = document.getElementById('tt-count');
        function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

        function mode() { var r = radios.filter(function (x) { return x.checked; })[0]; return r ? r.value : 'me'; }
        function showPane() {
            [].slice.call(form.querySelectorAll('[data-pane-for]')).forEach(function (p) { p.hidden = p.dataset.paneFor !== mode(); });
            list.hidden = true; count.textContent = 'Check who this will go to before sending.';
        }
        radios.forEach(function (r) { r.addEventListener('change', showPane); });
        showPane();

        // Only the chosen mode's fields, so a hidden pane's values don't confuse the check
        function params() {
            var fd = new FormData(form), q = new URLSearchParams(), m = mode();
            var keep = { me: [], emails: ['emails'], accounts: ['accounts'], bookmarks: [], team: ['role_id'], category: ['status[]', 'type', 'market_id', 'limit'] }[m];
            q.append('mode', m); q.append('sample', fd.get('sample') || '');
            keep.forEach(function (k) { fd.getAll(k).forEach(function (v) { q.append(k, v); }); });
            return q;
        }

        document.getElementById('tt-check').addEventListener('click', function () {
            count.textContent = 'Checking…';
            fetch(urls.recipients + '?' + params(), { headers: { Accept: 'application/json' } }).then(function (r) { return r.json().then(function (j) { return [r.ok, j]; }); }).then(function (res) {
                var ok = res[0], j = res[1];
                if (!ok) { count.innerHTML = '<span style="color:var(--bad)">' + esc(j.error || 'Could not check that.') + '</span>'; list.hidden = true; return; }
                var over = j.count > j.max;
                count.innerHTML = '<b>' + j.count + '</b> ' + (j.count === 1 ? 'person' : 'people') + (j.target ? ' · ' + esc(j.target) : '') + (over ? ' <span style="color:var(--bad)">— over the limit of ' + j.max + '; narrow it down</span>' : '');
                list.querySelector('tbody').innerHTML = j.recipients.map(function (r) {
                    return '<tr><td>' + esc(r.name || '—') + '</td><td>' + esc(r.email) + '</td><td class="mono">' + esc(r.account || '—') + '</td><td class="muted">' + esc(r.fills) + '</td></tr>';
                }).join('') || '<tr><td colspan="4" class="empty">Nobody with an email address matches.</td></tr>';
                list.hidden = false;
            }).catch(function () { count.textContent = 'Could not check right now.'; });
        });

        // Live preview: sample account, prefix and note
        var frame = document.getElementById('tt-frame'), timer;
        function preview() {
            var q = new URLSearchParams({ sample: form.sample.value, prefix: form.prefix.value, note: form.note.value });
            fetch(urls.preview + '?' + q, { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (j) {
                document.getElementById('tt-subject').textContent = j.subject;
                document.getElementById('tt-as').innerHTML = esc(j.customer.name) + ' <span class="mono muted">' + esc(j.customer.account) + '</span>' + (j.customer.found ? '' : ' <span class="muted">(that account wasn\'t found; showing the first account)</span>');
                document.getElementById('tt-sample-name').textContent = j.customer.found ? j.customer.name : 'not found';
                frame.srcdoc = '<!doctype html><html><head><meta charset="utf-8"><style>body{margin:0;font-family:Arial,sans-serif}</style></head><body>' + j.html + '</body></html>';
            }).catch(function () {});
        }
        ['sample', 'prefix', 'note'].forEach(function (n) { form[n].addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(preview, 350); }); });
        preview();

        [].slice.call(document.querySelectorAll('[data-width]')).forEach(function (b) {
            b.addEventListener('click', function () {
                document.querySelectorAll('[data-width]').forEach(function (x) { x.classList.toggle('on', x === b); });
                frame.classList.toggle('phone', b.dataset.width === 'phone');
            });
        });
    })();
    </script>
@endsection
