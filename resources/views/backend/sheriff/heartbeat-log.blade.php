@extends('backend.layouts.app')

@section('crumb', 'Control Log')

{{-- Every heartbeat control anyone used (restart, take down, clear…), newest first. Each row opens that check. --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'Control Log', 'sub' => 'Every restart, reboot, take down, bring up, clear and retry used on a heartbeat check: who, when and what came back.'])

    <select class="pill-select" aria-label="Check" style="margin-bottom:18px" onchange="location.href=this.value">
        <option value="{{ route('sheriff.heartbeat.log') }}">All checks</option>
        @foreach (\App\Support\Health::CHECKS as $k => [$n])<option value="{{ route('sheriff.heartbeat.log', ['check' => $k]) }}" @selected($check === $k)>{{ $n }}</option>@endforeach
    </select>

    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>When</th><th>Check</th><th>Control</th><th>Who</th><th>Result</th></tr></thead>
        <tbody>@forelse ($log as $a)
            @php $url = route('sheriff.heartbeat.show', $a->check_key); @endphp
            <tr class="click" data-href="{{ $url }}"><td class="nowrap"><a href="{{ $url }}">{{ $a->created_at->format('n/j/Y g:i A') }}</a></td><td><b>{{ $a->checkName() }}</b></td><td>{{ $a->label() }}</td>
                <td>{{ $a->user?->name ?? 'System' }}</td>
                <td class="wrap">@include('backend.partials.pill', ['text' => $a->ok ? 'Done' : 'Failed', 'tone' => $a->ok ? 'ok' : 'bad'])<div class="help">{{ \Illuminate\Support\Str::limit(strtok((string) $a->output, "\n"), 120) }}</div></td></tr>
        @empty <tr><td colspan="5" class="empty">No controls used yet.</td></tr> @endforelse</tbody>
    </table></div>@include('backend.partials.pager', ['p' => $log])</div>
@endsection
