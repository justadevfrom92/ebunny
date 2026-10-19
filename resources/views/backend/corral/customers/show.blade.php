@extends('backend.layouts.app')

@section('crumb', 'Account '.$c->account)

{{-- The Corral account page, in the same order as the original. --}}
@section('content')
    @php $money = fn ($v) => $v < 0 ? '($'.number_format(abs($v), 2).')' : '$'.number_format($v, 2); @endphp

    @include('backend.partials.page-head', [
        'title' => $c->name,
        'sub' => 'Account <span class="mono">'.e($c->account).'</span> · Ticket <span class="mono">'.e($c->ticket).'</span> · '
            .view('backend.partials.pill', ['text' => $c->status, 'tone' => $c->statusTone()])->render(),
        'actions' => '<form method="post" action="'.route('corral.customers.bookmark', $c).'" class="inline">'.csrf_field().'<button class="btn ghost">'.($bookmarked ? '★ Bookmarked' : '☆ Bookmark').'</button></form>'
            .'<a class="btn ghost" href="'.route('corral.customers.ticket', $c).'">Account Ticket</a>',
    ])

    <nav class="jump" aria-label="Sections">
        @foreach (['actions' => 'Actions', 'general' => 'General', 'products' => 'Products', 'address' => 'Address', 'stars' => 'Stars', 'details' => 'Details', 'files' => 'Files', 'notes' => 'Notes', 'calls' => 'Calls & Texts', 'bills' => 'Bills & Payments', 'plans' => 'Plans & Addresses', 'ercot' => 'ERCOT', 'tech' => 'TECH', 'warehouse' => 'Warehouse', 'logs' => 'Logs'] as $id => $label)
            <a href="#{{ $id }}">{{ $label }}</a>
        @endforeach
    </nav>

    {{-- Alerts from flags, and the protection cross-sell --}}
    @foreach ($banners as [$bTitle, $bText])
        <div class="alert bad"><b>{{ $bTitle }}</b> {{ $bText }}</div>
    @endforeach
    @if ($crossSell)
        <div class="alert info"><b>Generic Protection</b> {{ config('brand.name') }} has partnered with AIG to help customers avoid costly home repairs: heating and cooling, electrical system and surge protection. Ask if they're interested.</div>
    @endif

    {{-- Flags · Balances · Actions --}}
    <div class="grid-3" id="actions">
        <div class="panel" id="flags"><div class="panel-head"><h2>Flags</h2><a class="btn sm ghost" href="{{ route('corral.customers.panel-log', [$c, 'flags']) }}">Log</a></div><div class="panel-body">
            @forelse ($c->flags as $f)
                <div class="row-line"><span><b>{{ $f->flag }}</b><br><span class="help">{{ $f->created_at->format('Y-m-d H:i:s') }} · {{ $f->author ?? 'System' }}</span></span>
                    <form method="post" action="{{ route('corral.flags.remove', $f) }}" class="inline">@csrf @method('delete')<button class="link-btn">[ remove ]</button></form></div>
            @empty <p class="muted">No flags.</p> @endforelse
            <form method="post" action="{{ route('corral.customers.flags.add', $c) }}" class="form-row" style="margin-top:12px">@csrf
                <select name="flag" aria-label="Add flag" required><option value="">-- New Flag --</option>@foreach (config('corral.flags') as $flag)<option>{{ $flag }}</option>@endforeach</select>
                <button class="btn sm">Add</button>
            </form>
        </div></div>

        <div class="panel" id="balances"><div class="panel-head"><h2>Balances</h2><a class="btn sm ghost" href="{{ route('corral.customers.panel-log', [$c, 'balances']) }}">Log</a></div><div class="panel-body">
            <dl class="kv"><dt>Current Balance</dt><dd><b>{{ $money($c->balance) }}</b></dd><dt>Due Date</dt><dd>{{ $c->due_date?->format('n/j/Y') ?? '—' }}</dd></dl>
            <div class="actions" style="margin:12px 0">
                <a class="btn sm" href="{{ route('corral.customers.action', [$c, 'make-payment']) }}">Make Payment</a>
                <a class="btn sm ghost" href="{{ route('corral.customers.action', [$c, 'add-bill-credit']) }}">Add Credit</a>
            </div>
            @foreach (['credit' => 'Pending Credits', 'debit' => 'Pending Debits'] as $kind => $heading)
                <h3>{{ $heading }}</h3>
                @forelse ($pending->where('kind', $kind) as $e)
                    <div class="row-line"><span>{{ $money($e->amount) }} — {{ $e->description }}</span>
                        <form method="post" action="{{ route('corral.ledger.delete', $e) }}" class="inline" data-confirm="Delete this pending {{ $kind }}?|{{ $money($e->amount) }} — {{ $e->description }}|Delete">@csrf @method('delete')<button class="link-btn">[ delete ]</button></form></div>
                @empty <p class="help">None</p> @endforelse
            @endforeach
            @php $recentBalance = \App\Http\Controllers\Backend\Corral\PanelLogController::balanceRows($c)->take(10); @endphp
            <h3 style="margin-top:12px">Last 10</h3>
            @forelse ($recentBalance as $r)
                <div class="row-line"><span>{{ $r['type'] }} {{ $money($r['amount']) }}<br><span class="help">{{ $r['at']?->format('n/j/Y') }} · {{ $r['status'] }}</span></span></div>
            @empty <p class="help">No credits, debits or payments yet.</p> @endforelse
        </div></div>

        <div class="panel"><div class="panel-head"><h2>Actions</h2><a class="btn sm ghost" href="{{ route('corral.customers.panel-log', [$c, 'actions']) }}">Log</a></div><div class="panel-body" style="display:flex;flex-direction:column;gap:14px">
            @foreach (['status' => '-- Status --', 'service' => '-- Customer Service --'] as $group => $placeholder)
                <form method="get" class="form-row" data-action-form="{{ route('corral.customers.action', [$c, '__action__']) }}">
                    <select required aria-label="{{ $group === 'status' ? 'Status action' : 'Customer service action' }}"><option value="">{{ $placeholder }}</option>
                        @foreach (config('corral.actions.'.$group) as $key => $def)
                            @if (! isset($def[2]) || auth()->user()->hasPerm($def[2]))<option value="{{ $key }}">{{ $def[0] }}</option>@endif
                        @endforeach
                    </select>
                    <button class="btn sm">Go</button>
                </form>
            @endforeach
            <div><h3>Last 10</h3>
                @forelse (\App\Http\Controllers\Backend\Corral\PanelLogController::actionNotes($c)->limit(10)->get() as $n)
                    <div class="row-line"><span><b>{{ $n->action }}</b><br><span class="help">{{ $n->created_at->format('n/j/Y g:i A') }} · {{ $n->author ?? 'System' }}</span></span></div>
                @empty <p class="help">No actions run yet.</p> @endforelse</div>
        </div></div>
    </div>

    {{-- General Information --}}
    <div class="grid-2" id="general">
        <div class="panel"><div class="panel-head"><h2>Contact Information</h2><a class="btn sm ghost" href="{{ route('corral.customers.contact-log', $c) }}">Contact Log</a></div><div class="panel-body">
            <dl class="kv">
                <dt>IP</dt><dd class="mono">{{ $c->ip ?? '—' }}</dd>
                <dt>IP Location</dt><dd>{{ $c->ip_location ?? '—' }}</dd>
                <dt>First Name</dt><dd>{{ $c->first_name }}</dd>
                <dt>Last Name</dt><dd>{{ $c->last_name }}</dd>
                <dt>Email</dt><dd>{{ $c->email }}</dd>
                <dt>Phone</dt><dd>@include('backend.partials.phone-actions', ['phone' => $c->phone, 'c' => $c])</dd>
                <dt>Phone type</dt><dd>{{ $c->phone_type ?? '—' }}</dd>
                <dt>SSN</dt><dd class="mono">{{ $c->ssn_last4 ? '***-**-'.$c->ssn_last4 : '—' }}</dd>
                <dt>TEC Score</dt><dd>{{ $c->tec_score ?? '—' }}</dd>
                <dt>Status Info</dt><dd>{{ $c->status_info ?? '—' }}</dd>
                <dt>Language</dt><dd>{{ $c->language }}</dd>
                <dt>Username</dt><dd>{{ $c->username ?? '—' }} <a href="{{ route('corral.customers.action', [$c, 'reset-login']) }}">[ reset ]</a></dd>
                <dt>Linked Accounts</dt><dd>{{ collect($c->linked_accounts)->implode(', ') ?: '(none)' }}</dd>
                <dt>Authorized Users</dt><dd>
                    @forelse ($c->authorized_users ?? [] as $u){{ $u['name'] }} · {{ $u['phone'] }}<br>@empty <span class="muted">(none)</span> @endforelse
                    <a href="{{ route('corral.customers.action', [$c, 'add-authorized-user']) }}">[ add ]</a></dd>
            </dl>
        </div></div>

        <div class="panel" id="products"><div class="panel-head"><h2>Products</h2><a class="btn sm ghost" href="{{ route('corral.customers.log', [$c, 'products']) }}">Product Log</a></div><div class="panel-body">
            @forelse ($c->products as $p)
                <div class="row-line"><span><b>{{ $p->product }}</b> <span class="help">added {{ $p->created_at->format('n/j/Y') }}</span>
                        @if ($p->data)<br><span class="help">{{ collect($p->data)->map(fn ($v, $k) => str_replace('_', ' ', $k).': '.$v)->implode(' · ') }}</span>@endif</span>
                    <form method="post" action="{{ route('corral.products.remove', $p) }}" class="inline" data-confirm="Remove {{ $p->product }}?|It will no longer be on this account.|Remove">@csrf @method('delete')<button class="link-btn">[ remove ]</button></form></div>
            @empty <p class="muted">No products.</p> @endforelse
            <form method="post" action="{{ route('corral.customers.products.add', $c) }}" class="form-row" style="margin-top:12px">@csrf
                <select name="product" required aria-label="Add product"><option value="">-- Add Product --</option>
                    @foreach (array_keys(config('corral.products')) as $name)@unless ($c->products->contains('product', $name))<option>{{ $name }}</option>@endunless @endforeach
                </select><button class="btn sm">Add</button>
            </form>
        </div></div>
    </div>

    {{-- Service address · Billing address · Stars --}}
    <div class="grid-3" id="address">
        <div class="panel"><div class="panel-head"><h2>Service Address</h2></div><div class="panel-body">
            <dl class="kv">
                <dt>Street</dt><dd>{{ $c->address }}</dd><dt>Unit</dt><dd>{{ $c->unit ?? '—' }}</dd>
                <dt>City</dt><dd>{{ $c->city }}, TX {{ $c->zip }}</dd><dt>Utility</dt><dd>{{ $c->market?->description }}</dd>
                <dt>ESIID</dt><dd class="mono">{{ $c->esiid ?? '—' }}</dd><dt>Meter Number</dt><dd class="mono">{{ $c->meter_number ?? '—' }}</dd>
                <dt>Load Profile</dt><dd class="mono">{{ $c->load_profile ?? '—' }}</dd><dt>Load Zone</dt><dd>{{ $c->load_zone ?? '—' }}</dd>
                <dt>Customer Type</dt><dd>{{ $c->type === 'Residential' ? 'R' : 'C' }}</dd><dt>Meter Type</dt><dd>{{ $c->meter_type ?? '—' }}</dd>
            </dl>
            <div class="actions" style="margin-top:12px"><a class="btn sm ghost" href="{{ route('corral.customers.action', [$c, 'transfer-service']) }}">Transfer Service</a><a class="btn sm ghost" href="{{ route('corral.customers.action', [$c, 'move-out']) }}">Move Out</a></div>
        </div></div>
        <div class="panel"><div class="panel-head"><h2>Billing Address</h2></div><div class="panel-body">
            <dl class="kv"><dt>Street</dt><dd>{{ $c->billing_street ?? $c->address }}</dd><dt>Unit</dt><dd>{{ $c->billing_unit ?? '—' }}</dd>
                <dt>City</dt><dd>{{ $c->billing_city ?? $c->city }}, {{ $c->billing_state ?? 'TX' }} {{ $c->billing_zip ?? $c->zip }}</dd></dl>
        </div></div>
        <div class="panel" id="stars"><div class="panel-head"><h2>Current Stars</h2><b style="font-size:1.4rem">{{ number_format($c->stars) }}</b></div><div class="panel-body">
            <div class="actions" style="margin-bottom:12px">
                <form method="post" action="{{ route('corral.customers.stars.recalculate', $c) }}" class="inline">@csrf<button class="btn sm ghost">Recalculate</button></form>
                <a class="btn sm" href="{{ route('corral.customers.stars', $c) }}">Spend Stars</a>
            </div>
            <h3>Rewards History</h3>
            <div class="table-wrap"><table><thead><tr><th>Date</th><th>Reward</th><th class="num">Stars</th><th class="num">Net</th></tr></thead><tbody>
                @forelse ($rewards->take(12) as $r)
                    <tr><td>{{ $r['date']->format('n/j/Y') }}</td><td>{{ $r['reason'] }}</td><td class="num">{{ $r['stars'] >= 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($r['stars'], 1), '0'), '.') }}</td><td class="num">{{ number_format($r['net'], 1) }}</td></tr>
                @empty <tr><td colspan="4" class="empty">No stars yet.</td></tr> @endforelse
            </tbody></table></div>
        </div></div>
    </div>

    {{-- Status · Plan · Marketing details --}}
    <div class="grid-3" id="details">
        <div class="panel"><div class="panel-head"><h2>Status Details</h2></div><div class="panel-body">
            <dl class="kv">
                <dt>Internal ID</dt><dd class="mono">{{ $c->ticket }}</dd><dt>Confirmation #</dt><dd class="mono">{{ $c->account }}</dd>
                <dt>Internal Status</dt><dd>{{ $c->status }}</dd><dt>Status Info</dt><dd>{{ $c->status_info ?? '—' }}</dd>
                <dt>Order Date</dt><dd>{{ $c->created_at->format('n/j/Y g:i A') }}</dd><dt>Move/Switch</dt><dd>{{ $c->move_switch }}</dd>
                <dt>Requested Start</dt><dd>{{ $c->requested_start?->format('n/j/Y') ?? '—' }}</dd><dt>Actual Start</dt><dd>{{ $c->actual_start?->format('n/j/Y') ?? '—' }}</dd>
                @if ($c->exception)<dt>Exception</dt><dd>@include('backend.partials.pill', ['text' => $c->exception, 'tone' => 'bad'])</dd>@endif
            </dl>
            <form method="post" action="{{ route('corral.customers.status', $c) }}" class="form-row" style="margin-top:12px">@csrf @method('patch')
                <select name="status" aria-label="Status">@foreach (array_keys(config('admin.customer_statuses')) as $s)<option @selected($s === $c->status)>{{ $s }}</option>@endforeach</select>
                <button class="btn sm">Update</button>
            </form>
        </div></div>
        @php $term = $c->planTerms->firstWhere('status', 'current') ?? $c->planTerms->first(); @endphp
        <div class="panel"><div class="panel-head"><h2>Plan Details</h2></div><div class="panel-body">
            <dl class="kv">
                <dt>Plan Order Date</dt><dd>{{ $term?->ordered_at->format('n/j/Y g:i A') ?? '—' }}</dd>
                <dt>Plan Name</dt><dd>{{ $c->plan?->name ?? '—' }}</dd>
                <dt>Rate ID</dt><dd class="mono">{{ $c->rate_class ?? '—' }}</dd>
                <dt>Rate</dt><dd>{{ $term ? number_format($term->rate_2000, 5).'¢ @ 2000 kwh' : '—' }}</dd>
                <dt>Term</dt><dd>{{ $c->plan ? ($c->plan->term === 1 ? 'month-to-month' : $c->plan->term.' months') : '—' }}</dd>
                <dt>ETF</dt><dd>{{ $c->plan ? ($c->plan->etfAmount() ? '$'.number_format($c->plan->etfAmount(), 2) : 'none') : '—' }}</dd>
            </dl>
            <div class="actions" style="margin-top:12px"><a class="btn sm" href="{{ route('corral.orders.create', ['renew' => $c->account]) }}">Renew / Change Plan</a></div>
        </div></div>
        <div class="panel"><div class="panel-head"><h2>Marketing Details</h2></div><div class="panel-body">
            <form method="post" action="{{ route('corral.customers.marketing', $c) }}">@csrf @method('patch')
                <div class="form-grid" style="grid-template-columns:110px 1fr">
                    <label for="channel">Channel</label><input id="channel" name="channel" value="{{ $c->channel }}">
                    <label for="msid">MSID</label><input id="msid" name="msid" value="{{ $c->msid }}">
                    <label for="promo">Promo Code</label><input id="promo" name="promo_code" value="{{ $c->promo_code }}">
                </div>
                <button class="btn sm" style="margin-top:10px">Save</button>
            </form>
        </div></div>
    </div>

    {{-- Files --}}
    <div class="panel" id="files"><div class="panel-head"><h2>Files</h2><span class="muted">{{ $c->files->count() }}</span></div><div class="panel-body">
        <form method="post" action="{{ route('corral.customers.files.upload', $c) }}" enctype="multipart/form-data" class="form-row" style="margin-bottom:12px">@csrf
            <input type="file" name="file" required aria-label="File to upload"><button class="btn sm">Upload</button><span class="help">PDF, image, text or Word, up to 10 MB.</span>
        </form>
        <div class="table-wrap" style="max-height:320px;overflow-y:auto"><table><tbody>@foreach ($c->files->sortByDesc('created_at') as $f)
            <tr class="click" data-href="{{ route('corral.files.show', $f) }}"><td style="width:18ch">{{ $f->created_at->format('n/j/Y g:i A') }}</td>
                <td><a href="{{ route('corral.files.show', $f) }}">{{ $f->name }}</a></td>
                <td class="muted">{{ strtoupper($f->kind) }}</td></tr>
        @endforeach</tbody></table></div>
    </div></div>

    {{-- Notes --}}
    <div class="panel" id="notes"><div class="panel-head"><h2>Notes ({{ $c->notes->count() }})</h2></div><div class="panel-body">
        <form method="post" action="{{ route('corral.customers.notes', $c) }}" style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px">@csrf
            <div class="form-row">
                <select name="category" aria-label="Category" data-note-category><option value="">-- Category --</option>@foreach (array_keys(config('corral.note_categories')) as $cat)<option>{{ $cat }}</option>@endforeach</select>
                <select name="action" aria-label="Action" data-note-action><option value="">-- Action --</option>
                    @foreach (config('corral.note_categories') as $cat => $acts)<optgroup label="{{ $cat }}">@foreach ($acts as $a)<option>{{ $a }}</option>@endforeach</optgroup>@endforeach
                </select>
                <select name="priority" aria-label="Priority"><option value="">-- Priority --</option>@foreach (config('corral.priorities') as $p)<option>{{ $p }}</option>@endforeach</select>
            </div>
            <textarea name="body" required placeholder="New note" aria-label="New note" style="min-height:70px"></textarea>
            <div><button class="btn">Add Note</button></div>
        </form>
        @forelse ($c->notes as $n)
            <div class="note"><small>{{ $n->author }} · {{ $n->created_at->format('n/j/Y g:i A') }}@if ($n->category) · {{ $n->category }}@endif @if ($n->action) · {{ $n->action }}@endif @if ($n->priority) · @include('backend.partials.pill', ['text' => $n->priority, 'tone' => ['High' => 'bad', 'Medium' => 'warn'][$n->priority] ?? ''])@endif</small>{{ $n->body }}</div>
        @empty <p class="muted">No notes yet.</p> @endforelse
    </div></div>

    {{-- Phone calls and text messages with this customer --}}
    <div class="grid-2" id="calls" style="align-items:start">
        <div class="panel"><div class="panel-head"><h2>Phone Calls ({{ $calls->count() }})</h2><span class="actions"><a class="btn sm ghost" href="{{ route('corral.calls', ['account' => $c->account]) }}">All Calls</a><a class="btn sm cyan" href="{{ route('corral.contacts.create', ['account' => $c->account]) }}">New Contact</a></span></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Called</th><th>Issue</th><th class="num">Duration</th><th>Answered By</th><th>Resolution</th></tr></thead>
                <tbody>@forelse ($calls->take(10) as $p)
                    <tr class="click" data-href="{{ route('corral.calls.transcript', $p) }}"><td class="nowrap"><a href="{{ route('corral.calls.transcript', $p) }}">{{ $p->started_at->format('n/j/Y g:i A') }}</a><br><span class="help">{{ ucfirst($p->direction) }}</span></td><td>{{ $p->disposition ?? '—' }}</td>
                        <td class="num">{{ intdiv($p->duration_sec, 60) }}:{{ str_pad((string) ($p->duration_sec % 60), 2, '0', STR_PAD_LEFT) }}</td>
                        <td>{{ $p->user?->name ?? '—' }}@if ($p->agent_id)<br><span class="help mono">{{ $p->agent_id }}</span>@endif</td>
                        <td>@include('backend.partials.resolution-pill', ['value' => $p->resolution])</td></tr>
                @empty <tr><td colspan="5" class="empty">No calls yet.</td></tr> @endforelse</tbody>
            </table></div>
        </div>
        <div class="panel"><div class="panel-head"><h2>Texts ({{ $texts->count() }})</h2><span class="actions"><a class="btn sm ghost" href="{{ route('corral.sms', ['account' => $c->account]) }}">All Texts</a>@if ($c->phone)<a class="btn sm cyan" href="{{ route('corral.sms.create', ['account' => $c->account]) }}">Send a Text</a>@endif</span></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Sent</th><th>Direction</th><th>Message</th><th>Resolution</th></tr></thead>
                <tbody>@forelse ($texts->take(10) as $m)
                    <tr class="click" data-href="{{ route('corral.sms.show', $m) }}"><td class="nowrap"><a href="{{ route('corral.sms.show', $m) }}">{{ $m->created_at->format('n/j/Y g:i A') }}</a></td><td>{{ $m->direction === 'in' ? 'From customer' : 'To customer' }}</td><td class="wrap">{{ $m->body }}</td><td>@include('backend.partials.resolution-pill', ['value' => $m->resolution])</td></tr>
                @empty <tr><td colspan="4" class="empty">No texts yet.</td></tr> @endforelse</tbody>
            </table></div>
        </div>
    </div>

    {{-- Bills & Payments --}}
    <div id="bills" style="display:flex;flex-direction:column;gap:20px">
        <div class="grid-2">
            <div class="panel"><div class="panel-head"><h2>Billing Summary</h2></div><div class="table-wrap" style="max-height:360px;overflow-y:auto"><table>
                <thead><tr><th>Date</th><th class="num">Billed</th><th class="num">Paid</th><th>Notes</th></tr></thead>
                <tbody>@forelse ($summary as $s)<tr><td>{{ $s['date']->format('n/j/Y') }}</td><td class="num">{{ $s['billed'] !== null ? $money($s['billed']) : '-' }}</td><td class="num">{{ $s['paid'] !== null ? $money($s['paid']) : '-' }}</td><td class="mono">{{ $s['note'] }}</td></tr>
                @empty <tr><td colspan="4" class="empty">No billing yet{{ $c->bills->isEmpty() ? ': service hasn\'t started, the first bill comes about 30 days after the switch' : '' }}.</td></tr> @endforelse</tbody>
            </table></div></div>
            <div class="panel"><div class="panel-head"><h2>Usage History</h2><span class="muted">kWh</span></div><div class="table-wrap"><table>
                <thead><tr><th>Year</th>@foreach (range(1, 12) as $m)<th class="num">{{ date('M', mktime(0, 0, 0, $m, 1)) }}</th>@endforeach</tr></thead>
                <tbody>@forelse ($usage as $year => $months)<tr><td><b>{{ $year }}</b></td>@foreach (range(1, 12) as $m)<td class="num">{{ isset($months[$m]) ? number_format($months[$m]) : '-' }}</td>@endforeach</tr>
                @empty <tr><td colspan="13" class="empty">No usage yet{{ $c->bills->isEmpty() ? ': usage shows after the first bill' : '' }}.</td></tr> @endforelse</tbody>
            </table></div></div>
        </div>

        <div class="panel"><div class="panel-head"><h2>Bill Details</h2></div><div class="table-wrap"><table>
            <thead><tr><th>Item</th><th>Type</th><th>Period</th><th class="num">Usage</th><th>Invoice Date</th><th>Due Date</th><th>Paid Date</th><th class="num">Amount Billed</th><th class="num">Amount Paid</th><th class="num">Balance</th><th>Invoice</th><th>Ontime</th></tr></thead>
            <tbody>@forelse ($c->bills as $b)
                <tr><td class="mono">{{ $b->reference }}</td><td>{{ $b->bill_type }}</td><td>{{ $b->period_start?->format('n/j/Y') }} - {{ $b->period_end?->format('n/j/Y') }}</td><td class="num">{{ number_format($b->kwh) }}</td>
                    <td>{{ $b->billed_on->format('n/j/Y') }}</td><td>{{ $b->due_on?->format('n/j/Y') }}</td><td>{{ $b->paid_on?->format('n/j/Y') ?? '—' }}</td>
                    <td class="num">{{ $money($b->amount) }}</td><td class="num">{{ $money($b->amount_paid) }}</td><td class="num">{{ $money($b->balance_after) }}</td><td class="mono">{{ $b->invoice }}</td><td>{{ $b->ontime ? 'Yes' : 'No' }}</td></tr>
            @empty <tr><td colspan="12" class="empty">No bills yet{{ $c->bills->isEmpty() ? ': service hasn\'t started' : '' }}.</td></tr> @endforelse</tbody>
        </table></div></div>

        <div class="panel" id="payments"><div class="panel-head"><h2>Payment Details</h2>
            <span class="actions"><a class="btn sm ghost" href="{{ request()->fullUrlWithQuery(['all_payments' => $showAllPayments ? null : 1]) }}#payments">{{ $showAllPayments ? 'Hide invalid transactions' : 'Show all transactions' }}</a>
                <a class="btn sm ghost" href="{{ route('corral.customers.action', [$c, 'transfer-payments']) }}">Transfer Payments</a></span></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Item</th><th>Date</th><th>Time</th><th>Source</th><th>Account</th><th>Type</th><th>Status</th><th class="num">Amount</th><th>Confirmation #</th></tr></thead>
                <tbody>@forelse ($payments as $p)
                    <tr><td class="mono">{{ $p->reference }}</td><td>{{ $p->paid_on->toDateString() }}</td><td>{{ $p->paid_time ? \Illuminate\Support\Carbon::parse($p->paid_time)->format('h:i:s A') : '' }}</td><td>{{ $p->source }}</td>
                        <td>{{ $p->paymentMethod?->nickname ?? $p->paymentMethod?->type ?? 'Unknown account' }}@if ($p->paymentMethod?->last4)<br><span class="help">ending {{ $p->paymentMethod->last4 }}</span>@endif</td>
                        <td>{{ $p->kind }}</td><td>@include('backend.partials.pill', ['text' => $p->status, 'tone' => ['Success' => 'ok', 'Failed' => 'bad', 'Reversed' => 'warn', 'Pending' => 'warn'][$p->status] ?? ''])</td>
                        <td class="num">{{ $money($p->amount) }}</td>
                        <td class="mono">{{ $p->confirmation }}@if ($p->status === 'Reversed') Reversed {{ $p->reversed_at?->format('n/j/Y') }}@endif
                            @if ($canRefund && $p->status === 'Success')
                                <form method="post" action="{{ route('corral.payments.reverse', $p) }}" class="inline" data-confirm="Reverse payment?|{{ $p->reference }} for {{ $money($p->amount) }} will be reversed and added back to the balance.|Reverse Payment">@csrf<button class="link-btn">[ reverse ]</button></form>
                            @endif</td></tr>
                @empty <tr><td colspan="9" class="empty">No payments yet{{ $c->bills->isEmpty() ? ': nothing is due until the first bill' : '' }}.</td></tr> @endforelse</tbody>
            </table></div>
        </div>

        <div class="panel" id="payment-methods"><div class="panel-head"><h2>Payment Methods</h2>
            <a class="btn sm ghost" href="{{ request()->fullUrlWithQuery(['all_methods' => $showAllMethods ? null : 1]) }}#payment-methods">{{ $showAllMethods ? 'Hide deleted methods' : 'Show all methods' }}</a></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Item</th><th>Date</th><th>Type</th><th>Expires</th><th>Nickname</th><th>Vendor</th><th class="num">Total Paid</th><th>Autopay</th><th></th></tr></thead>
                <tbody>@forelse ($methods as $m)
                    <tr @class(['muted' => $m->removed_at])><td class="mono">{{ $m->id }}</td><td>{{ $m->created_at->toDateString() }}</td><td>{{ $m->type }} - {{ $m->last4 }}</td><td>{{ $m->expires ?? '—' }}</td><td>{{ $m->nickname }}</td><td>{{ $m->vendor }}</td>
                        <td class="num">{{ $money($methodTotals[$m->id] ?? 0) }}</td><td>{{ $m->autopay ? 'Yes' : '' }}</td>
                        <td>@unless ($m->removed_at)
                            <form method="post" action="{{ route('corral.methods.remove', $m) }}" class="inline" data-confirm="Remove payment method?|{{ $m->label() }}|Remove">@csrf @method('delete')<button class="link-btn">[ remove ]</button></form>
                            @unless ($m->autopay)<form method="post" action="{{ route('corral.methods.autopay', $m) }}" class="inline">@csrf<button class="link-btn">[ set autopay ]</button></form>@endunless
                        @else removed @endunless</td></tr>
                @empty <tr><td colspan="9" class="empty">No payment methods.</td></tr> @endforelse</tbody>
            </table></div>
        </div>
    </div>

    {{-- Plans & Addresses --}}
    <div class="panel" id="plans"><div class="panel-head"><h2>Plan Change Log</h2></div><div class="table-wrap"><table>
        <thead><tr><th>Date Ordered</th><th>Contract Start</th><th>Contract End</th><th>Actual Term</th><th>Rate</th><th>Energy Charge</th><th>Plan</th><th></th></tr></thead>
        <tbody>@forelse ($c->planTerms as $t)
            <tr><td>{{ $t->ordered_at->format('Y-m-d H:i:s') }}</td><td>{{ $t->contract_start?->format('n/j/Y') ?? '—' }}</td><td>{{ $t->contract_end?->format('n/j/Y') ?? '—' }}</td>
                <td>@include('backend.partials.pill', ['text' => $t->status === 'current' && $t->contract_start ? 'current - '.(int) $t->contract_start->diffInMonths(today()).' months in' : $t->status, 'tone' => ['current' => 'ok', 'ended' => ''][$t->status] ?? 'info'])</td>
                <td>{{ number_format($t->rate_2000, 1) }} ¢ @ 2,000</td><td class="mono">{{ number_format($t->energy_charge, 8) }}</td>
                <td>{{ $t->plan?->name }}<br><span class="mono help">{{ $t->rate_class }}</span></td>
                <td><form method="post" action="{{ route('corral.plan-terms.action', $t) }}" class="form-row">@csrf
                    <select name="do" aria-label="Plan action" data-reveal-when="edit-rate"><option value="welcome">Send Welcome Packet</option><option value="new-welcome">Send New Welcome Packet</option>@if ($t->status !== 'current')<option value="activate">Activate</option>@endif<option value="edit-rate">Edit Rate</option></select>
                    <input name="energy_charge" type="number" step="0.00000001" min="0" value="{{ $t->energy_charge }}" aria-label="Energy charge" style="width:12ch" hidden>
                    <button class="btn sm">Go</button></form></td></tr>
        @empty <tr><td colspan="8" class="empty">No plans.</td></tr> @endforelse</tbody>
    </table></div></div>

    <div class="panel"><div class="panel-head"><h2>Address Change Log</h2></div><div class="table-wrap"><table>
        <thead><tr><th>Date Ordered</th><th>Service Start</th><th>Service End</th><th>Service Length</th><th>ESIID</th><th>Address</th><th>Change dates</th></tr></thead>
        <tbody>@foreach ($c->addresses as $a)
            <tr><td>{{ $a->ordered_at->format('Y-m-d H:i:s') }}</td><td>{{ $a->service_start?->format('n/j/Y') ?? '—' }}</td><td>{{ $a->service_end?->format('n/j/Y') ?? '—' }}</td>
                <td>{{ $a->service_start ? ($a->service_end ? (int) $a->service_start->diffInMonths($a->service_end).' months' : 'current - '.(int) $a->service_start->diffInMonths(today()).' months in') : 'not started' }}</td>
                <td class="mono">{{ $a->esiid }}</td><td>{{ strtoupper($a->street) }}<br>{{ $a->city }}, TX {{ $a->zip }}</td>
                <td><form method="post" action="{{ route('corral.addresses.update', $a) }}" class="form-row">@csrf @method('patch')
                    <input type="date" name="service_start" value="{{ $a->service_start?->toDateString() }}" aria-label="Move-in date">
                    <input type="date" name="service_end" value="{{ $a->service_end?->toDateString() }}" aria-label="Move-out date">
                    <button class="btn sm">Save</button></form></td></tr>
        @endforeach</tbody>
    </table></div></div>

    {{-- ERCOT --}}
    <div class="panel" id="ercot"><div class="panel-head"><h2>ERCOT Transactions</h2><span class="mono muted">{{ strtoupper($c->address) }}, {{ strtoupper($c->city) }} {{ $c->zip }} · {{ $c->esiid }}</span></div><div class="table-wrap"><table>
        <thead><tr><th>Date Saved</th><th>Trans Date</th><th>Purpose</th><th>Scheduled</th><th>Trans Type</th><th>Tracking</th><th>Status</th><th></th></tr></thead>
        <tbody>@forelse ($c->ercotTransactions as $t)
            <tr><td>{{ $t->created_at->format('Y-m-d H:i:s') }}</td><td>{{ $t->trans_date->format('n/j/Y') }}</td><td>{{ $t->purpose }}</td><td>{{ $t->scheduled_on?->format('n/j/Y') ?? '—' }}</td>
                <td><span class="mono">{{ $t->trans_type }}</span> {{ $t->label }}</td><td class="mono">{{ $t->tracking ?? '—' }}</td><td>{{ $t->status }}</td>
                <td>@if ($t->status === 'linked')<form method="post" action="{{ route('corral.ercot.action', $t) }}" class="form-row">@csrf
                    <select name="do" aria-label="Transaction action"><option value="unlink">Unlink</option>@if ($t->purpose === 'request')<option value="cancel">Cancel</option>@endif</select><button class="btn sm">Go</button></form>@endif</td></tr>
        @empty <tr><td colspan="8" class="empty">No ERCOT transactions.</td></tr> @endforelse</tbody>
    </table></div></div>

    {{-- TECH --}}
    <div class="panel" id="tech"><div class="panel-head"><h2>TECH · Move Queues</h2></div><div class="panel-body" style="display:flex;flex-direction:column;gap:10px">
        @forelse ($currentQueues as $q)
            <form method="post" action="{{ route('corral.queues.move', $q) }}" class="form-row">@csrf @method('patch')
                <span class="pill info mono">{{ $q->queue }}</span>
                <select name="queue" aria-label="Move from {{ $q->queue }}"><option value="remove">- - Remove This Queue - -</option>@foreach (config('corral.queues') as $name)<option @selected($name === $q->queue)>{{ $name }}</option>@endforeach</select>
                <button class="btn sm">Save</button>
            </form>
        @empty <p class="muted">Not in any queue.</p> @endforelse
        <form method="post" action="{{ route('corral.customers.queues.add', $c) }}" class="form-row">@csrf
            <select name="queue" required aria-label="Add queue"><option value="">- - Add Queue - -</option>@foreach (config('corral.queues') as $name)<option>{{ $name }}</option>@endforeach</select>
            <button class="btn sm">Add</button>
        </form>
        <h3 style="margin-top:8px">Details</h3>
        <div class="table-wrap" style="max-height:300px;overflow-y:auto"><table><tbody>@foreach ($c->queueLogs as $q)
            <tr><td style="width:22ch">{{ $q->entered_at->format('n/j/Y h:i:s A') }}</td><td class="mono">queuelog_{{ \Illuminate\Support\Str::snake(\Illuminate\Support\Str::after($q->queue, 'Queue')) }}s</td><td>{{ $q->note }}</td></tr>
            @if ($q->exited_at)<tr><td>{{ $q->exited_at->format('n/j/Y h:i:s A') }}</td><td class="mono">queuelog_{{ \Illuminate\Support\Str::snake(\Illuminate\Support\Str::after($q->queue, 'Queue')) }}s_out</td><td></td></tr>@endif
        @endforeach</tbody></table></div>
    </div></div>

    {{-- Warehouse --}}
    <div class="panel" id="warehouse"><div class="panel-head"><h2>Warehouse</h2><span class="muted">updated {{ now()->format('n/j/Y \a\t g:i A') }}</span></div>
        <div class="panel-body"><dl class="kv warehouse">@foreach ($warehouse as $k => $v)<dt class="mono">{{ $k }}</dt><dd class="mono">{{ $v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : $v }}</dd>@endforeach</dl></div>
    </div>

    {{-- Logs --}}
    <div class="panel" id="logs"><div class="panel-head"><h2>Logs</h2></div><div class="panel-body actions">
        <a class="btn ghost" href="{{ route('corral.customers.api-log', $c) }}">API Log</a>
        <a class="btn ghost" href="{{ route('corral.customers.contact-log', $c) }}">Contact Log</a>
        <a class="btn ghost" href="{{ route('corral.customers.log', [$c, 'attributes']) }}">Attribute Log</a>
        <a class="btn ghost" href="{{ route('corral.customers.ticket', $c) }}">Child Items</a>
        @foreach ($logs as $l)<a class="btn ghost" href="{{ route('corral.customers.log', [$c, $l['key']]) }}">{{ $l['title'] }} <span class="count-pill">{{ $l['count'] }}</span></a>@endforeach
    </div></div>
@endsection
