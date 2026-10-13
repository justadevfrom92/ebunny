@extends('backend.layouts.app')

@section('crumb', $agent->exists ? 'Edit '.$agent->name : 'New Automation')

{{-- Add or change a cron automation: what it does, when it runs, which model, its instructions --}}
@section('content')
    @include('backend.partials.page-head', ['title' => $agent->exists ? 'Edit '.$agent->name : 'New Automation', 'sub' => 'An AI agent that runs on a cron schedule in the background. Models are added in Deputy.'])
    <form method="post" action="{{ $agent->exists ? route('sheriff.automations.update', $agent) : route('sheriff.automations.store') }}" class="panel simple-form">@csrf @if ($agent->exists) @method('put') @endif
        <div class="panel-body form-grid">
            <label for="name">Name</label><input id="name" name="name" required maxlength="80" value="{{ old('name', $agent->name) }}">
            <span class="label">Does</span>
            <div class="table-wrap"><table class="table pick-table"><tbody>@foreach ($activities as $v => $l)
                <tr><td><input type="radio" id="activity-{{ $v }}" name="activity" value="{{ $v }}" required @checked(old('activity', $agent->activity) === $v)></td><td><label for="activity-{{ $v }}">{{ $l }}</label></td></tr>
            @endforeach</tbody></table></div>
            <label for="schedule">Schedule</label><div><input id="schedule" name="schedule" required maxlength="60" class="mono" value="{{ old('schedule', $agent->schedule) }}" placeholder="*/15 * * * *" style="max-width:20ch">
                <div class="help">Cron: */15 * * * * is every 15 minutes, 0 * * * * every hour, 30 6 * * * daily at 6:30am.</div></div>
            <label for="description">Description</label><input id="description" name="description" maxlength="250" value="{{ old('description', $agent->description) }}">
            <span class="label">Model</span>
            <div class="table-wrap"><table class="table pick-table"><thead><tr><th></th><th>Model</th><th>Where</th><th>Status</th></tr></thead><tbody>@foreach ($models as $m)
                <tr><td><input type="radio" id="model-{{ $m->id }}" name="ai_model_id" value="{{ $m->id }}" required @checked(old('ai_model_id', $agent->ai_model_id) == $m->id)></td><td><label for="model-{{ $m->id }}">{{ $m->name }}</label> <span class="mono help">{{ $m->model_id }}</span></td><td>{{ $m->providerLabel() }}</td><td>{{ ucfirst($m->status) }}</td></tr>
            @endforeach</tbody></table></div>
            <span class="label">Fallback model</span>
            <div class="table-wrap"><table class="table pick-table"><tbody>
                <tr><td><input type="radio" id="fallback-none" name="fallback_model_id" value="" @checked(! old('fallback_model_id', $agent->fallback_model_id))></td><td colspan="2"><label for="fallback-none">None</label></td></tr>
                @foreach ($models as $m)<tr><td><input type="radio" id="fallback-{{ $m->id }}" name="fallback_model_id" value="{{ $m->id }}" @checked(old('fallback_model_id', $agent->fallback_model_id) == $m->id)></td><td><label for="fallback-{{ $m->id }}">{{ $m->name }}</label> <span class="mono help">{{ $m->model_id }}</span></td><td>{{ $m->providerLabel() }}</td></tr>@endforeach
            </tbody></table></div>
            <label for="temperature">Temperature</label><div><input id="temperature" name="temperature" type="number" step="0.05" min="0" max="2" required value="{{ old('temperature', $agent->temperature) }}" style="max-width:10ch"><div class="help">Keep it low (0 to 0.3) so background fixes are consistent.</div></div>
            <label for="max_tokens">Max tokens</label><input id="max_tokens" name="max_tokens" type="number" min="256" max="64000" required value="{{ old('max_tokens', $agent->max_tokens) }}" style="max-width:14ch">
            <label for="system_prompt">Instructions</label><textarea id="system_prompt" name="system_prompt" required maxlength="20000" style="min-height:220px;font-family:inherit">{{ old('system_prompt', $agent->system_prompt) }}</textarea>
            <label for="active">On</label><label class="check"><input type="hidden" name="active" value="0"><input id="active" type="checkbox" name="active" value="1" @checked(old('active', $agent->active))> Runs on its schedule</label>
        </div>
        <div class="panel-body actions"><button class="btn cyan">{{ $agent->exists ? 'Save Automation' : 'Add Automation' }}</button><a class="btn ghost" href="{{ $agent->exists ? route('sheriff.automations.show', $agent) : route('sheriff.automations') }}">Cancel</a></div>
    </form>
    <script>
    document.querySelectorAll('.pick-table tbody tr').forEach(function (tr) { tr.addEventListener('click', function (e) { if (e.target.tagName !== 'INPUT') { var i = tr.querySelector('input'); if (i) i.checked = true; } }); });
    </script>
@endsection
