@extends('backend.layouts.app')

@section('crumb', $email->template)

@section('content')
    @php $c = $email->customer; @endphp
    @include('backend.partials.page-head', ['title' => $email->template,
        'sub' => 'Email <span class="mono">'.$email->id.'</span>'.($c ? ' to <span class="mono">'.e($c->account).'</span> · '.e($c->name).' · '.e($c->email) : ''),
        'actions' => ($c ? '<a class="btn ghost" href="'.route('rodeo.emails.sent', ['account' => $c->account]).'">All Emails to This Account</a>' : '').($template ? '<a class="btn ghost" href="'.route('rodeo.templates.edit', $template).'">Template</a><a class="btn ghost" href="'.route('rodeo.templates.heatmap', $template).'">Click Heat Map</a>' : '')])

    @if ($suppression)<div class="flash bad" role="status" style="margin-bottom:16px">{{ $suppression->email }} is on the suppression list ({{ \App\Models\EmailSuppression::REASONS[$suppression->reason] ?? $suppression->reason }}, {{ $suppression->created_at->format('n/j/Y') }}). No more emails go to it.</div>@endif

    <div class="it-grid">
        <div class="panel">
            <div class="panel-head"><h2>What the customer got</h2>@include('backend.rodeo.emails._stage', ['e' => $email])</div>
            <div class="panel-body">
                @if ($template && $c)<p style="margin:0 0 10px"><b>Subject:</b> {{ $template->renderSubject($c) }}</p>@endif
                @if ($html)<iframe title="Email" sandbox="" srcdoc="{{ $html }}" style="width:100%;min-height:440px;border:1px solid var(--line);border-radius:6px;background:#fff"></iframe>
                @else<p class="muted" style="margin:0">The body of this email wasn't kept, and there's no Rodeo template named “{{ $email->template }}” to show instead.</p>@endif
            </div>
        </div>
        <div class="it-side">
            <div class="panel"><div class="panel-head"><h2>Timeline</h2></div>
                <div class="table-wrap"><table><tbody>
                    <tr><td>Created</td><td class="nowrap">{{ $email->created_at->format('n/j/Y g:i:s A') }}</td></tr>
                    <tr><td>Delivered</td><td class="nowrap">{{ $email->sent_at?->format('n/j/Y g:i:s A') ?? ($email->status === 'queued' ? 'Waiting in the queue' : 'Not sent') }}</td></tr>
                    <tr><td>Opened</td><td class="nowrap">{{ $email->opened_at?->format('n/j/Y g:i:s A') ?? '—' }}</td></tr>
                    <tr><td>Clicked</td><td class="nowrap">{{ $email->clicked_at?->format('n/j/Y g:i:s A') ?? '—' }}</td></tr>
                    @if ($email->dropped_at)<tr><td>Dropped</td><td class="nowrap">{{ $email->dropped_at->format('n/j/Y g:i:s A') }}</td></tr>@endif
                    <tr><td>Sent by</td><td>{{ $email->campaign ? 'Campaign: ' : '' }}@if ($email->campaign)<a href="{{ route('rodeo.campaigns.edit', $email->campaign) }}">{{ $email->campaign->name }}</a>@else{{ $email->user?->name ?? 'System' }}@endif</td></tr>
                </tbody></table></div>
                @if ($c?->email && ! $suppression)<div class="panel-body"><a class="btn sm ghost" href="{{ route('rodeo.emails.suppressions.create', ['email' => $c->email]) }}">Suppress this address</a></div>@endif
            </div>
            <div class="panel"><div class="panel-head"><h2>Other emails to this account</h2>@if ($c)<a class="btn sm ghost" href="{{ route('rodeo.emails.sent', ['account' => $c->account]) }}">All</a>@endif</div>
                <div class="table-wrap"><table><tbody>@forelse ($others as $o)
                    <tr class="click" data-href="{{ route('rodeo.emails.sent.show', $o) }}"><td class="nowrap">{{ $o->created_at->format('n/j/Y') }}</td><td><a href="{{ route('rodeo.emails.sent.show', $o) }}">{{ $o->template }}</a></td><td>@include('backend.rodeo.emails._stage', ['e' => $o])</td></tr>
                @empty <tr><td class="empty">None.</td></tr> @endforelse</tbody></table></div>
            </div>
        </div>
    </div>
@endsection
