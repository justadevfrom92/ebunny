@extends('backend.layouts.app')

@section('crumb', 'Test '.$agent->name)

@section('content')
    @include('backend.partials.page-head', ['title' => 'Test '.$agent->name, 'sub' => e($agent->activityLabel()).' · runs on '.e($agent->model?->name).($agent->fallback ? ', falling back to '.e($agent->fallback->name) : '').'. Each test is a real call to the model and is saved as a Test conversation.',
        'actions' => '<a class="btn ghost" href="'.route('deputy.agents.edit', $agent).'">Edit Agent</a>'])

    @if ($r = session('agent_result'))
        <div class="panel" style="margin-bottom:20px"><div class="panel-head"><h2>Reply</h2>
            @include('backend.partials.pill', $r['ok'] ? ['text' => 'Answered by '.$r['model_name'], 'tone' => 'ok'] : ['text' => 'No answer', 'tone' => 'bad'])</div>
            <div class="panel-body transcript">
                <p><b>You</b> {{ old('message') }}</p>
                @if ($r['ok'])<p class="agent" style="white-space:pre-wrap"><b>{{ $agent->name }}</b> {{ $r['text'] }}</p>@endif
                @if ($r['note'])<p class="muted" style="background:none;padding:0">{{ $r['note'] }}</p>@endif
                <p class="muted" style="background:none;padding:0;font-size:.8rem">{{ number_format($r['ms']) }} ms · {{ number_format($r['input_tokens']) }} tokens in · {{ number_format($r['output_tokens']) }} out · <a href="{{ route('deputy.conversations.show', $r['conversation']) }}">saved conversation</a></p>
            </div>
        </div>
    @endif

    <form method="post" action="{{ route('deputy.agents.test.run', $agent) }}" class="panel simple-form" style="margin-bottom:20px">@csrf
        <div class="panel-body form-grid" style="grid-template-columns:1fr">
            <label for="message">Message to the agent</label>
            <textarea id="message" name="message" required maxlength="4000" style="min-height:120px;font-family:inherit" placeholder="Write what a customer might say, e.g. Why is my bill higher this month?">{{ session('agent_result') ? '' : old('message') }}</textarea>
        </div>
        <div class="panel-body actions"><button class="btn cyan">Send to Agent</button></div>
    </form>

    <div class="panel"><div class="panel-head"><h2>Recent Tests</h2></div><div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>When</th><th>By</th><th>Model</th><th>Outcome</th></tr></thead>
        <tbody>@forelse ($tests as $t)
            @php $u = route('deputy.conversations.show', $t); @endphp<tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $t->id }}</b></a></td><td class="nowrap">{{ $t->started_at->format('n/j/Y g:i A') }}</td><td>{{ $t->user?->name }}</td><td>{{ $t->model?->name ?? '—' }}</td><td>@include('backend.deputy._outcome', ['c' => $t])</td></tr>
        @empty <tr><td colspan="5" class="empty">Not tested yet.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
