{{-- One ticket in a list: the whole row opens it --}}
@php [$sLabel, $sTone] = \App\Models\SupportTicket::STATUSES[$t->status]; [$pLabel, $pTone] = \App\Models\SupportTicket::PRIORITIES[$t->priority]; @endphp
<tr class="click" data-href="{{ route('ranger.tickets.show', $t) }}">
    <td class="nowrap"><a class="mono" href="{{ route('ranger.tickets.show', $t) }}">{{ $t->number() }}</a><div class="help">{{ \App\Models\SupportTicket::CHANNELS[$t->channel] ?? '' }} {{ $t->channel }} · {{ $t->created_at->diffForHumans() }}</div></td>
    <td class="wrap"><b>{{ $t->subject }}</b><div class="help">{{ \App\Models\SupportTicket::TOPICS[$t->topic] ?? $t->topic }}</div></td>
    <td>{{ $t->name }}@if ($t->customer)<div class="help">@include('backend.partials.account-link', ['c' => $t->customer])</div>@endif</td>
    <td>@include('backend.partials.pill', ['text' => $sLabel, 'tone' => $sTone])@if ($t->priority !== 'normal') @include('backend.partials.pill', ['text' => $pLabel, 'tone' => $pTone])@endif</td>
    @unless ($compact ?? false)
    <td>{{ $t->assignee?->name ?? '—' }}</td>
    <td class="nowrap">@if ($t->rating){{ $t->rating === 'good' ? '👍' : '👎' }}@else<span class="muted">—</span>@endif</td>
    @endunless
</tr>
