@extends('admin.layouts.app')

@section('crumb', $agent->exists ? $agent->name : 'New Agent')

@section('content')
    @include('admin.partials.page-head', ['title' => $agent->exists ? 'Edit '.$agent->name : 'New Agent', 'sub' => 'What the agent does, which model it runs on, and its instructions.',
        'actions' => $agent->exists ? '<a class="btn ghost" href="'.route('deputy.agents.test', $agent).'">Test</a>' : null])
    <form method="post" action="{{ $agent->exists ? route('deputy.agents.update', $agent) : route('deputy.agents.store') }}" class="panel simple-form">@csrf @if ($agent->exists) @method('put') @endif
        <div class="panel-body form-grid">
            <label for="name">Name</label><input id="name" name="name" required maxlength="80" value="{{ old('name', $agent->name) }}">
            <span class="label">Activity</span>
            <div class="table-wrap"><table class="table pick-table">
                <thead><tr><th></th><th>Activity</th><th>Kind</th></tr></thead>
                <tbody>@foreach (config('deputy.activities') as $v => $l)
                    @php $talks = in_array($v, config('deputy.conversation_activities'), true); @endphp
                    <tr><td><input type="radio" id="activity-{{ $v }}" name="activity" value="{{ $v }}" data-talks="{{ $talks ? 1 : 0 }}" required @checked(old('activity', $agent->activity ?? 'chat') === $v)></td>
                        <td><label for="activity-{{ $v }}">{{ $l }}</label></td>
                        <td class="help">{{ $talks ? 'Talks to customers ('.($v === 'sms' ? 'texts' : 'My Account chat bubble').')' : 'Automation: runs on a schedule' }}</td></tr>
                @endforeach</tbody>
            </table></div>
            <label for="schedule" class="sched">Schedule</label><div class="sched"><input id="schedule" name="schedule" maxlength="60" class="mono" value="{{ old('schedule', $agent->schedule) }}" placeholder="*/15 * * * *" style="max-width:20ch"><div class="help">Cron schedule for automations: */15 * * * * is every 15 minutes, 0 * * * * every hour, 30 6 * * * daily at 6:30am.</div></div>
            <label for="description">Description</label><input id="description" name="description" maxlength="250" value="{{ old('description', $agent->description) }}">
            <label for="ai_model_id">Model</label><select id="ai_model_id" name="ai_model_id">@foreach ($models->groupBy(fn ($m) => $m->providerLabel()) as $p => $list)<optgroup label="{{ $p }}">@foreach ($list as $m)<option value="{{ $m->id }}" @selected(old('ai_model_id', $agent->ai_model_id) == $m->id)>{{ $m->name }} ({{ $m->model_id }}){{ $m->status !== 'available' ? ' — '.$m->status : '' }}</option>@endforeach</optgroup>@endforeach</select>
            <label for="fallback_model_id">Fallback model</label><div><select id="fallback_model_id" name="fallback_model_id"><option value="">None</option>@foreach ($models->groupBy(fn ($m) => $m->providerLabel()) as $p => $list)<optgroup label="{{ $p }}">@foreach ($list as $m)<option value="{{ $m->id }}" @selected(old('fallback_model_id', $agent->fallback_model_id) == $m->id)>{{ $m->name }} ({{ $m->model_id }})</option>@endforeach</optgroup>@endforeach</select><div class="help">Used when the main model can't answer (not set up, unreachable or erroring).</div></div>
            <label for="temperature">Temperature</label><div><input id="temperature" name="temperature" type="number" step="0.05" min="0" max="2" required value="{{ old('temperature', $agent->temperature) }}" style="max-width:10ch"><div class="help">0 gives the same careful answer every time; higher is more varied. 0.2 to 0.4 suits customer service.</div></div>
            <label for="max_tokens">Max tokens</label><input id="max_tokens" name="max_tokens" type="number" min="256" max="64000" required value="{{ old('max_tokens', $agent->max_tokens) }}" style="max-width:14ch">
            <label for="system_prompt">Instructions</label><textarea id="system_prompt" name="system_prompt" required maxlength="20000" style="min-height:220px;font-family:inherit">{{ old('system_prompt', $agent->system_prompt) }}</textarea>
            <label for="active">Active</label><label class="check"><input type="hidden" name="active" value="0"><input id="active" type="checkbox" name="active" value="1" @checked(old('active', $agent->active))> On (answering customers, or running on its schedule)</label>
        </div>
        <div class="panel-body actions"><button class="btn cyan">{{ $agent->exists ? 'Save Agent' : 'Add Agent' }}</button><a class="btn ghost" href="{{ route('deputy.agents') }}">Cancel</a></div>
    </form>
    <script>
    // The schedule only applies to automations
    (function () {
        function sync() { var r = document.querySelector('input[name=activity]:checked'); var auto = r && r.dataset.talks === '0';
            document.querySelectorAll('.sched').forEach(function (el) { el.hidden = !auto; }); }
        document.querySelectorAll('input[name=activity]').forEach(function (r) { r.addEventListener('change', sync); }); sync();
        document.querySelectorAll('.pick-table tbody tr').forEach(function (tr) { tr.addEventListener('click', function (e) { if (e.target.tagName !== 'INPUT') { var i = tr.querySelector('input'); i.checked = true; i.dispatchEvent(new Event('change')); } }); });
    })();
    </script>
@endsection
