@extends('backend.layouts.app')

@section('crumb', $t->number())

{{-- One ticket: the conversation (replies the customer sees, internal notes they don't), reply box, and status / owner / priority on the side. --}}
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
    @endphp
    @include('backend.partials.page-head', ['title' => $t->subject, 'sub' => '<span class="mono">'.$t->number().'</span> · '.view('backend.partials.pill', ['text' => $sLabel, 'tone' => $sTone])->render()
        .' · '.e(\App\Models\SupportTicket::TOPICS[$t->topic] ?? $t->topic).' · opened '.$t->created_at->format('n/j/Y g:i A').' from '.e($t->channel),
        'actions' => $actions ?: null])

    <div class="grid-2" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr);align-items:start">
        <div style="display:flex;flex-direction:column;gap:20px;min-width:0">
            @if ($t->manager_requested_at)
                <div class="alert warn"><b>{{ $t->managerRequester?->name ?? 'Someone' }} asked for a manager</b> {{ $t->manager_requested_at->diffForHumans() }}@if ($t->manager_request_note): “{{ $t->manager_request_note }}”@endif
                    @if (! $manager && $canWork)<form method="post" action="{{ route('ranger.tickets.cancel-manager', $t) }}" class="inline">@csrf<button class="btn sm ghost">No Longer Needed</button></form>@endif</div>
            @endif

            @php $thread = $t->thread(); @endphp
            <div class="panel"><div class="panel-head"><h2>Conversation</h2><span class="muted">{{ $thread->where('kind', '!=', 'system')->count() }} messages</span></div>
                <div class="panel-body rt-thread">
                    @foreach ($thread as $m)
                        @if ($m->kind === 'system')
                            <p class="rt-system">{{ $m->body }} <span>{{ $m->at->format('n/j g:i A') }}</span></p>
                        @else
                            <div class="rt-msg rt-{{ $m->kind }}">
                                <div class="rt-who"><b>{{ $m->kind === 'customer' ? $t->name : ($m->user?->name ?? 'Support') }}</b>
                                    @if ($m->kind === 'note')<span class="pill warn">Internal note</span>@if ($m->priority)@include('backend.partials.pill', ['text' => $m->priority.' priority', 'tone' => ['High' => 'bad', 'Medium' => 'warn'][$m->priority] ?? ''])@endif<span class="help">on the account's notes</span>
                                    @elseif ($m->kind === 'agent' && $team($m->user?->id))<span class="help">{{ $team($m->user->id) }}</span>@endif
                                    <span class="help">{{ $m->at->format('n/j/Y g:i A') }}</span></div>
                                <div class="rt-body">{!! nl2br(e($m->body)) !!}</div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            @if ($canWork)
                <form method="post" action="{{ route('ranger.tickets.reply', $t) }}" class="panel">@csrf
                    <div class="panel-head"><h2>Reply</h2>
                        <div class="chips small"><label><input type="radio" name="kind" value="agent" checked><span>Reply to customer</span></label><label><input type="radio" name="kind" value="note"><span>Internal note</span></label></div></div>
                    <div class="panel-body" style="display:flex;flex-direction:column;gap:12px">
                        <textarea name="body" required maxlength="5000" style="min-height:120px" aria-label="Message" placeholder="Hi {{ \Illuminate\Support\Str::before($t->name, ' ') }}, …"></textarea>
                        <div class="field rt-note-only"><label>Note priority</label><div class="chips small">@foreach (config('corral.priorities') as $pr)<label><input type="radio" name="priority" value="{{ $pr }}" @checked($loop->first)><span>{{ $pr }}</span></label>@endforeach</div>
                            <span class="help">Internal notes are saved as account notes (category Ticket) on {{ $t->customer ? 'account '.$t->customer->account : 'the ticket' }}.</span></div>
                        <div class="field"><label>Then set the ticket to</label><div class="chips small"><label><input type="radio" name="status" value="" checked><span>Leave as is</span></label>
                            <label><input type="radio" name="status" value="waiting"><span>Waiting on Customer</span></label><label><input type="radio" name="status" value="solved"><span>Solved</span></label></div></div>
                        <div><button class="btn cyan">Send</button> <span class="help">Replies show in the customer's My Account; internal notes never do.</span></div>
                    </div>
                </form>
            @else
                <div class="alert info"><b>{{ $t->assignee?->name }} has this ticket.</b> A manager can join you in or take it over.</div>
            @endif
        </div>

        <div style="display:flex;flex-direction:column;gap:20px;min-width:0">
            <div class="panel"><div class="panel-head"><h2>Ticket</h2></div><div class="panel-body">
                <dl class="kv">
                    <dt>Owner</dt><dd>{{ $t->assignee?->name ?? 'Unassigned' }}@if ($t->assigned_to && $team($t->assigned_to))<div class="help">{{ $team($t->assigned_to) }}{{ $t->assignee?->company ? ' · '.$t->assignee->company : '' }}</div>@endif</dd>
                    <dt>Joined</dt><dd>@forelse ($t->watchers as $w){{ $w->name }}@if (! $loop->last), @endif @empty — @endforelse</dd>
                    <dt>First reply</dt><dd>{{ $t->first_response_at ? $t->created_at->diffForHumans($t->first_response_at, true).' later' : 'Not yet' }}</dd>
                    @if ($t->solved_at)<dt>Solved</dt><dd>{{ $t->solved_at->format('n/j/Y g:i A') }}</dd>@endif
                    <dt>Rating</dt><dd>@if ($t->rating){{ $t->rating === 'good' ? '👍 Helped' : '👎 Didn\'t help' }}@if ($t->rating_comment)<div class="help">“{{ $t->rating_comment }}”</div>@endif @else<span class="muted">{{ $t->isDone() ? 'Not rated yet' : 'Asked when solved' }}</span>@endif</dd>
                </dl>
            </div>
                <form method="post" action="{{ route('ranger.tickets.update', $t) }}" class="panel-body" style="border-top:1px solid var(--line);display:flex;flex-direction:column;gap:12px">@csrf @method('patch')
                    <div class="field"><label>Status</label><div class="chips small">@foreach (\App\Models\SupportTicket::STATUSES as $k => [$l])<label><input type="radio" name="status" value="{{ $k }}" @checked($t->status === $k)><span>{{ $l }}</span></label>@endforeach</div></div>
                    <div class="field"><label>Priority</label><div class="chips small">@foreach (\App\Models\SupportTicket::PRIORITIES as $k => [$l])<label><input type="radio" name="priority" value="{{ $k }}" @checked($t->priority === $k)><span>{{ $l }}</span></label>@endforeach</div></div>
                    @if ($manager)
                        <div class="field"><label>Assign to</label><input type="search" class="search-box" placeholder="Find someone" data-live-filter="#rt-assign" aria-label="Find a team member">
                            <div class="table-wrap pick-list" style="max-height:200px" id="rt-assign"><table class="table"><tbody>
                                @foreach ($agents as $a)<tr data-filter-row="{{ strtolower($a->user->name.' '.\App\Models\RangerAgent::TEAMS[$a->team]) }}"><td style="width:32px"><input type="radio" id="as{{ $a->user_id }}" name="assigned_to" value="{{ $a->user_id }}" @checked($t->assigned_to === $a->user_id)></td>
                                    <td><label for="as{{ $a->user_id }}"><b>{{ $a->user->name }}</b></label> <span class="help">{{ \App\Models\RangerAgent::TEAMS[$a->team] }}{{ $a->level === 'manager' ? ' · Manager' : '' }}</span></td></tr>@endforeach
                            </tbody></table></div></div>
                    @endif
                    <div><button class="btn">Save</button></div>
                </form>
                @if (! $manager && $canWork && ! $t->manager_requested_at && ! $t->isDone())
                    <form method="post" action="{{ route('ranger.tickets.ask-manager', $t) }}" class="panel-body" style="border-top:1px solid var(--line);display:flex;flex-direction:column;gap:8px">@csrf
                        <label for="rt-why"><b>Need help?</b> <span class="help">Managers assign tickets to other people, take over, or join in.</span></label>
                        <input id="rt-why" name="reason" maxlength="500" placeholder="Why (optional), e.g. customer wants a refund over $100">
                        <div><button class="btn cyan">Ask for a Manager</button></div>
                    </form>
                @endif
            </div>

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

        </div>
    </div>
@endsection
