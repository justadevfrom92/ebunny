@extends('backend.layouts.app')

@section('crumb', $t->number())

{{-- One ticket: its activity (My Account messages, emails and phone calls, with attachments), the reply box, status, owner and customer on the side, and the ticket's notes at the bottom. --}}
@section('content')
    @php
        $me = auth()->user();
        $owner = $t->assigned_to === $me->id;
        $joined = $t->watchers->contains('id', $me->id);
        $canWork = ! $t->assigned_to || $owner || $joined || $manager;
        [$sLabel, $sTone] = \App\Models\SupportTicket::STATUSES[$t->status];
        $team = fn ($uid) => isset($agentOf[$uid]) ? \App\Models\RangerAgent::TEAMS[$agentOf[$uid]->team] : null;
        $actions = '';
        if ($manager && ! $owner) {
            $actions .= '<form method="post" action="'.route('ranger.tickets.take-over', $t).'" class="inline" data-confirm="Take over '.$t->number().'?|It becomes yours'.($t->assignee ? '; '.e($t->assignee->name).' stays on it as a watcher' : '').'.|Take Over">'.csrf_field().'<button class="btn cyan">Take Over</button></form>';
            if (! $joined && $t->assigned_to) {
                $actions .= '<form method="post" action="'.route('ranger.tickets.join', $t).'" class="inline">'.csrf_field().'<button class="btn ghost">Join In</button></form>';
            }
        }
        if ($joined) {
            $actions .= '<form method="post" action="'.route('ranger.tickets.leave', $t).'" class="inline">'.csrf_field().'<button class="btn ghost">Leave</button></form>';
        }
        if (! $t->assigned_to) {
            $actions .= '<form method="post" action="'.route('ranger.tickets.update', $t).'" class="inline">'.csrf_field().method_field('patch').'<input type="hidden" name="assigned_to" value="'.$me->id.'"><button class="btn cyan">Pick Up</button></form>';
        }
        // Agents don't assign tickets; when they need a manager they ask from here
        if (! $manager && $canWork && ! $t->isDone()) {
            $actions .= $t->manager_requested_at
                ? '<form method="post" action="'.route('ranger.tickets.cancel-manager', $t).'" class="inline">'.csrf_field().view('backend.partials.pill', ['text' => 'Manager requested', 'tone' => 'warn'])->render().' <button class="btn ghost">No Longer Needed</button></form>'
                : '<a class="btn cyan" href="#need-help">Need Help?</a>';
        }
    @endphp
    @include('backend.partials.page-head', ['title' => $t->subject, 'sub' => '<span class="mono">'.$t->number().'</span> · '.view('backend.partials.pill', ['text' => $sLabel, 'tone' => $sTone])->render()
        .' · '.e(\App\Models\SupportTicket::TOPICS[$t->topic] ?? $t->topic).' · opened '.$t->created_at->format('n/j/Y g:i A').' from '.(\App\Models\SupportTicket::CHANNELS[$t->channel] ?? '').' '.e($t->channel),
        'actions' => $actions ?: null])

    @if (! $manager && $canWork && ! $t->isDone() && ! $t->manager_requested_at)
        {{-- Need Help? (top right) opens this: ask a manager to assign, take over or join in --}}
        <form method="post" action="{{ route('ranger.tickets.ask-manager', $t) }}" class="panel need-help" id="need-help">@csrf
            <div class="panel-head"><h2>Ask for a Manager</h2><a class="btn sm ghost" href="#">Cancel</a></div>
            <div class="panel-body" style="display:flex;flex-direction:column;gap:10px">
                <span class="help">A manager can assign this ticket to someone else, take it over, or join in. Your reason is saved as a note.</span>
                <input name="reason" maxlength="500" aria-label="Why" placeholder="Why (optional), e.g. customer wants a refund over $100">
                <div><button class="btn cyan">Ask for a Manager</button></div>
            </div>
        </form>
    @endif

    <div class="grid-2" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr);align-items:start">
        <div style="display:flex;flex-direction:column;gap:20px;min-width:0">
            @php
                $entries = $t->messages->where('author', '!=', 'system');
                $calls = $entries->where('via', 'phone')->count();
                $emails = $entries->where('via', 'email')->count();
                $msgs = $entries->count() - $calls - $emails;
                $counts = array_filter([$msgs ? $msgs.' '.Str::plural('message', $msgs) : null, $emails ? $emails.' '.Str::plural('email', $emails) : null, $calls ? $calls.' '.Str::plural('call', $calls) : null]);
            @endphp
            <div class="panel"><div class="panel-head"><h2>Activity</h2><span class="muted">{{ implode(' · ', $counts) ?: 'Nothing yet' }}</span></div>
                <div class="panel-body rt-thread">
                    @foreach ($t->messages as $m)
                        @if ($m->author === 'system')
                            <p class="rt-system">{{ $m->body }} <span>{{ $m->created_at->format('n/j g:i A') }}</span></p>
                        @elseif ($m->via === 'phone')
                            <div class="rt-msg rt-call">
                                <div class="rt-who"><b>📞 {{ $m->direction === 'in' ? 'Inbound call' : 'Outbound call' }}</b><span>{{ $m->durationLabel() }}</span>@if ($m->contact)<span class="help">{{ $m->contact }}</span>@endif
                                    <span class="help">{{ $m->user?->name ?? 'Support' }}{{ $team($m->user_id) ? ' · '.$team($m->user_id) : '' }} · {{ $m->created_at->format('n/j/Y g:i A') }}</span></div>
                                <div class="rt-body">{!! nl2br(e($m->body)) !!}</div>
                            </div>
                        @else
                            <div class="rt-msg rt-{{ $m->author }}">
                                <div class="rt-who"><b>{{ $m->author === 'customer' ? $t->name : ($m->user?->name ?? 'Support') }}</b>
                                    @if ($m->author === 'note')<span class="pill warn">Note</span>
                                    @else<span class="help">{{ \App\Models\SupportTicketMessage::VIA[$m->via][1] }} {{ $m->via === 'email' ? ($m->author === 'customer' ? 'Email from ' : 'Email to ').($m->contact ?? $t->email) : 'My Account' }}</span>@endif
                                    @if ($m->author === 'agent' && $team($m->user_id))<span class="help">{{ $team($m->user_id) }}</span>@endif
                                    <span class="help">{{ $m->created_at->format('n/j/Y g:i A') }}</span></div>
                                <div class="rt-body">{!! nl2br(e($m->body)) !!}</div>
                                @if ($m->attachments->isNotEmpty())
                                    <div class="rt-files">@foreach ($m->attachments as $a)<a href="{{ route('ranger.tickets.attachment', [$t, $a]) }}">{{ $a->icon() }} {{ $a->name }} <span class="help">{{ $a->sizeLabel() }}</span></a>@endforeach</div>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            @if ($canWork)
                <form method="post" action="{{ route('ranger.tickets.reply', $t) }}" class="panel rt-reply">@csrf
                    <div class="panel-head"><h2>Reply to the Customer</h2></div>
                    <div class="panel-body" style="display:flex;flex-direction:column;gap:12px">
                        <div class="field rt-agent-only"><label>Send by</label><div class="chips small">
                            <label><input type="radio" name="via" value="portal" @checked($t->channel === 'My Account')><span>💬 My Account</span></label>
                            <label><input type="radio" name="via" value="email" @checked($t->channel === 'Email')><span>✉ Email to {{ $t->email }}</span></label>
                            <label><input type="radio" name="via" value="phone" @checked($t->channel === 'Phone')><span>📞 Log a phone call</span></label></div></div>
                        <div class="rt-phone-only" style="display:flex;gap:16px;flex-wrap:wrap">
                            <div class="field"><label>Call</label><div class="chips small"><label><input type="radio" name="direction" value="out" checked><span>We called them</span></label><label><input type="radio" name="direction" value="in"><span>They called us</span></label></div></div>
                            <div class="field"><label for="rt-min">Minutes</label><input id="rt-min" name="minutes" type="number" min="0" max="600" value="5" style="width:90px"></div>
                        </div>
                        <textarea name="body" required maxlength="5000" style="min-height:120px" aria-label="Message" placeholder="Hi {{ \Illuminate\Support\Str::before($t->name, ' ') }}, …"></textarea>
                        <div class="field"><label>Then set the ticket to</label><div class="chips small"><label><input type="radio" name="status" value="" checked><span>Leave as is</span></label>
                            <label><input type="radio" name="status" value="waiting"><span>Waiting on Customer</span></label><label><input type="radio" name="status" value="solved"><span>Solved</span></label></div></div>
                        <div><button class="btn cyan">Save</button> <span class="help">Messages and emails reach the customer; call notes stay with the team.</span></div>
                    </div>
                </form>
            @else
                <div class="alert info"><b>{{ $t->assignee?->name }} has this ticket.</b> A manager can join you in or take it over.</div>
            @endif

            {{-- Notes: account notes (the Note model) written on this ticket, staff only --}}
            <div class="panel" id="notes"><div class="panel-head"><h2>Notes</h2><span class="muted">{{ $t->notes->count() }} · staff only, also on the account's notes</span></div>
                @if ($canWork && $t->customer_id)
                    @php $cats = config('corral.note_categories'); @endphp
                    <form method="post" action="{{ route('ranger.tickets.notes', $t) }}" class="panel-body rt-note-form" style="display:flex;flex-direction:column;gap:12px">@csrf
                        <div class="form-row note-selects">
                            <div class="field"><label for="rt-cat">Category</label><select id="rt-cat" name="category" required data-note-category>@foreach (array_keys($cats) as $cat)<option @selected(old('category', 'Ticket') === $cat)>{{ $cat }}</option>@endforeach</select></div>
                            <div class="field"><label for="rt-act">Action</label><select id="rt-act" name="action" required data-note-action>@foreach ($cats as $cat => $acts)<optgroup label="{{ $cat }}">@foreach ($acts as $act)<option @selected(old('action', 'Internal Note') === $act && old('category', 'Ticket') === $cat)>{{ $act }}</option>@endforeach</optgroup>@endforeach</select></div>
                            <div class="field"><label for="rt-pri">Priority</label><select id="rt-pri" name="priority" required>@foreach (config('corral.priorities') as $pr)<option @selected(old('priority', 'Low') === $pr)>{{ $pr }}</option>@endforeach</select></div>
                        </div>
                        <div class="field"><label for="rt-note">Note</label><textarea id="rt-note" name="body" required maxlength="5000" style="min-height:90px" placeholder="What the team should know about this ticket">{{ old('body') }}</textarea></div>
                        <div><button class="btn">Add Note</button></div>
                    </form>
                    <script>
                    (function () {   // Action lists only the chosen category's actions
                        var f = document.querySelector('.rt-note-form'), cat = f.querySelector('[data-note-category]'), act = f.querySelector('[data-note-action]');
                        function sync() {
                            [].forEach.call(act.querySelectorAll('optgroup'), function (g) { var on = g.label === cat.value; g.hidden = !on; g.disabled = !on; });
                            if (!act.selectedOptions[0] || act.selectedOptions[0].parentNode.disabled) { var first = act.querySelector('optgroup:not([disabled]) option'); if (first) first.selected = true; }
                        }
                        cat.addEventListener('change', sync); sync();
                    })();
                    </script>
                @endif
                <div class="table-wrap"><table data-page="10">
                    <thead><tr><th>Added</th><th>By</th><th>Category / Action</th><th>Priority</th><th>Note</th></tr></thead>
                    <tbody>@forelse ($t->notes->sortByDesc('created_at') as $n)
                        @php $nUrl = route('corral.items.show', ['id' => $n->id, 'model' => 'ItemNote_model']); @endphp
                        <tr class="click" data-href="{{ $nUrl }}"><td class="nowrap"><a href="{{ $nUrl }}">{{ $n->created_at->format('n/j/Y g:i A') }}</a></td><td>{{ $n->user?->name ?? $n->author }}</td>
                            <td>{{ $n->category ?? '—' }}@if ($n->action)<div class="help">{{ $n->action }}</div>@endif</td>
                            <td>@if ($n->priority)@include('backend.partials.pill', ['text' => $n->priority, 'tone' => ['High' => 'bad', 'Medium' => 'warn'][$n->priority] ?? ''])@else — @endif</td>
                            <td class="wrap" style="min-width:160px">{{ \Illuminate\Support\Str::limit($n->body, 200) }}</td></tr>
                    @empty <tr><td colspan="5" class="empty">No notes yet.</td></tr> @endforelse</tbody>
                </table></div>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:20px;min-width:0">
            <div class="panel"><div class="panel-head"><h2>Ticket</h2></div><div class="panel-body">
                <dl class="kv">
                    <dt>Came in by</dt><dd>{{ \App\Models\SupportTicket::CHANNELS[$t->channel] ?? '' }} {{ $t->channel }}</dd>
                    <dt>Owner</dt><dd>{{ $t->assignee?->name ?? 'Unassigned' }}@if ($t->assigned_to && $team($t->assigned_to))<div class="help">{{ $team($t->assigned_to) }}{{ $t->assignee?->company ? ' · '.$t->assignee->company : '' }}</div>@endif</dd>
                    <dt>Joined</dt><dd>@forelse ($t->watchers as $w){{ $w->name }}@if (! $loop->last), @endif @empty — @endforelse</dd>
                    <dt>First reply</dt><dd>{{ ! $t->first_response_at ? 'Not yet' : ($t->first_response_at->diffInMinutes($t->created_at, true) < 1 ? 'Answered live on the call' : $t->created_at->diffForHumans($t->first_response_at, true).' later') }}</dd>
                    @if ($t->solved_at)<dt>Solved</dt><dd>{{ $t->solved_at->format('n/j/Y g:i A') }}</dd>@endif
                    <dt>Rating</dt><dd>@if ($t->rating){{ $t->rating === 'good' ? '👍 Helped' : '👎 Didn\'t help' }}@if ($t->rating_comment)<div class="help">“{{ $t->rating_comment }}”</div>@endif @else<span class="muted">{{ $t->isDone() ? 'Not rated yet' : 'Asked when solved' }}</span>@endif</dd>
                </dl>
            </div>
                <form method="post" action="{{ route('ranger.tickets.update', $t) }}" class="panel-body" style="border-top:1px solid var(--line);display:flex;flex-direction:column;gap:12px">@csrf @method('patch')
                    <div class="field"><label>Status</label><div class="chips small">@foreach (\App\Models\SupportTicket::STATUSES as $k => [$l])<label><input type="radio" name="status" value="{{ $k }}" @checked($t->status === $k)><span>{{ $l }}</span></label>@endforeach</div></div>
                    <div class="field"><label>Priority</label><div class="chips small">@foreach (\App\Models\SupportTicket::PRIORITIES as $k => [$l])<label><input type="radio" name="priority" value="{{ $k }}" @checked($t->priority === $k)><span>{{ $l }}</span></label>@endforeach</div></div>
                    <div><button class="btn">Save</button></div>
                </form>
            </div>

            @if ($manager)
                <form method="post" action="{{ route('ranger.tickets.update', $t) }}" class="panel">@csrf @method('patch')
                    <div class="panel-head"><h2>Assign To</h2><span class="help">managers &amp; admins</span></div>
                    <div class="panel-body" style="display:flex;flex-direction:column;gap:12px">
                        <input type="search" class="search-box" placeholder="Find someone" data-live-filter="#rt-assign" aria-label="Find a team member">
                        <div class="table-wrap pick-list" style="max-height:220px" id="rt-assign"><table class="table"><tbody>
                            @foreach ($agents as $a)<tr data-filter-row="{{ strtolower($a->user->name.' '.\App\Models\RangerAgent::TEAMS[$a->team]) }}"><td style="width:32px"><input type="radio" id="as{{ $a->user_id }}" name="assigned_to" value="{{ $a->user_id }}" @checked($t->assigned_to === $a->user_id)></td>
                                <td><label for="as{{ $a->user_id }}"><b>{{ $a->user->name }}</b></label> <span class="help">{{ \App\Models\RangerAgent::TEAMS[$a->team] }}{{ $a->level === 'manager' ? ' · Manager' : '' }}</span></td></tr>@endforeach
                        </tbody></table></div>
                        <div><button class="btn">Assign</button></div>
                    </div>
                </form>
            @endif

            <div class="panel"><div class="panel-head"><h2>Customer</h2>@if ($t->customer_id)<a class="btn sm ghost" href="{{ route('ranger.tickets', ['view' => 'all', 'customer' => $t->customer_id]) }}">Ticket History · {{ $history }}</a>@endif</div><div class="panel-body"><dl class="kv">
                <dt>Name</dt><dd>{{ $t->name }}</dd>
                <dt>Email</dt><dd>{{ $t->email }}</dd>
                @if ($t->customer)
                    <dt>Account</dt><dd>@include('backend.partials.account-link', ['c' => $t->customer])</dd>
                    <dt>Phone</dt><dd>{{ $t->customer->phone ?? '—' }}</dd>
                    <dt>Status</dt><dd>@include('backend.partials.pill', ['text' => $t->customer->status, 'tone' => $t->customer->statusTone()])</dd>
                    <dt>Plan</dt><dd>{{ $t->customer->plan?->name ?? '—' }}</dd>
                    <dt>Balance</dt><dd>${{ number_format($t->customer->balance, 2) }}</dd>
                @endif
            </dl></div></div>

            @if ($t->attachments->isNotEmpty())
            <div class="panel"><div class="panel-head"><h2>Attachments</h2><span class="muted">{{ $t->attachments->count() }}</span></div><div class="table-wrap"><table data-page="10"><tbody>
                @forelse ($t->attachments->sortByDesc('created_at') as $a)
                    <tr class="click" data-href="{{ route('ranger.tickets.attachment', [$t, $a]) }}"><td class="wrap"><a href="{{ route('ranger.tickets.attachment', [$t, $a]) }}">{{ $a->icon() }} {{ $a->name }}</a>
                        <div class="help">{{ $a->sizeLabel() }} · {{ $a->from_customer ? 'from '.$t->name : 'sent by '.($a->user?->name ?? 'Support') }} · {{ $a->created_at->format('n/j/Y') }}</div></td></tr>
                @empty <tr><td class="empty">No files on this ticket.</td></tr> @endforelse
            </tbody></table></div></div>
            @endif
        </div>
    </div>

@endsection
