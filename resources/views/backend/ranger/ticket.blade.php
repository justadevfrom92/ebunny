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
            <div class="panel"><div class="panel-head"><h2>Conversation</h2><span class="muted">{{ $t->messages->where('author', '!=', 'system')->count() }} messages</span></div>
                <div class="panel-body rt-thread">
                    @foreach ($t->messages as $m)
                        @if ($m->author === 'system')
                            <p class="rt-system">{{ $m->body }} <span>{{ $m->created_at->format('n/j g:i A') }}</span></p>
                        @else
                            <div class="rt-msg rt-{{ $m->author }}">
                                <div class="rt-who"><b>{{ $m->author === 'customer' ? $t->name : ($m->user?->name ?? 'Support') }}</b>
                                    @if ($m->author === 'note')<span class="pill warn">Internal note</span>@elseif ($m->author === 'agent' && $team($m->user_id))<span class="help">{{ $team($m->user_id) }}</span>@endif
                                    <span class="help">{{ $m->created_at->format('n/j/Y g:i A') }}</span></div>
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
                    <dt>Rating</dt><dd>@if ($t->rating){{ $t->rating === 'good' ? '👍 Good' : '👎 Bad' }}@if ($t->rating_comment)<div class="help">“{{ $t->rating_comment }}”</div>@endif @else<span class="muted">{{ $t->isDone() ? 'Not rated yet' : 'Asked when solved' }}</span>@endif</dd>
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
            </div>

            <div class="panel"><div class="panel-head"><h2>Customer</h2></div><div class="panel-body"><dl class="kv">
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

            @if ($others->isNotEmpty())
                <div class="panel"><div class="panel-head"><h2>Their Other Tickets</h2></div><div class="table-wrap"><table><tbody>
                    @foreach ($others as $o)<tr class="click" data-href="{{ route('ranger.tickets.show', $o) }}"><td class="wrap"><a class="mono" href="{{ route('ranger.tickets.show', $o) }}">{{ $o->number() }}</a> {{ $o->subject }}<div class="help">{{ \App\Models\SupportTicket::STATUSES[$o->status][0] }} · {{ $o->created_at->format('n/j/Y') }}</div></td></tr>@endforeach
                </tbody></table></div></div>
            @endif
        </div>
    </div>
@endsection
