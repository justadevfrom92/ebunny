<?php

namespace App\Http\Controllers\Backend\Sheriff;

use App\Http\Controllers\Controller;
use App\Models\AiAgent;
use App\Models\AiAutomationRun;
use App\Models\AiModel;
use Cron\CronExpression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Sheriff → Cron Automations: AI agents that don't talk to customers and run on a
 * cron schedule (triage queues, fix enrollments, match payments...). Each has its own
 * page with its runs. The models they use are set up in Deputy.
 */
class AutomationController extends Controller
{
    public function index(): View
    {
        return view('backend.sheriff.automations.index', [
            'agents' => AiAgent::automations()->with(['model', 'fallback'])->orderByDesc('active')->orderBy('name')->get(),
            'week' => $this->stats(today()->subDays(6)),
            'last' => AiAutomationRun::whereIn('id', AiAutomationRun::selectRaw('max(id)')->groupBy('ai_agent_id'))->get()->keyBy('ai_agent_id'),
            'runs' => AiAutomationRun::with(['agent', 'model'])->latest('started_at')->latest('id')->limit(25)->get(),
        ]);
    }

    public function show(AiAgent $agent): View
    {
        $this->automation($agent);

        return view('backend.sheriff.automations.show', [
            'agent' => $agent->load(['model', 'fallback']),
            'week' => $this->stats(today()->subDays(6))[$agent->id] ?? null,
            'month' => $this->stats(today()->subDays(29))[$agent->id] ?? null,
            'runs' => $agent->runs()->with('model')->latest('started_at')->latest('id')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return $this->form(new AiAgent(['activity' => 'queues', 'schedule' => '*/15 * * * *', 'temperature' => 0.2, 'max_tokens' => 2000, 'active' => true]));
    }

    public function edit(AiAgent $agent): View
    {
        return $this->form($this->automation($agent));
    }

    public function store(Request $request): RedirectResponse
    {
        $agent = AiAgent::create($this->validated($request));

        return redirect()->route('sheriff.automations.show', $agent)->with('status', $agent->name.' added');
    }

    public function update(Request $request, AiAgent $agent): RedirectResponse
    {
        $this->automation($agent)->update($this->validated($request));

        return redirect()->route('sheriff.automations.show', $agent)->with('status', $agent->name.' saved');
    }

    /** Pause or resume. */
    public function toggle(AiAgent $agent): RedirectResponse
    {
        $this->automation($agent)->update(['active' => ! $agent->active]);

        return back()->with('status', $agent->name.($agent->active ? ' resumed' : ' paused'));
    }

    private function form(AiAgent $agent): View
    {
        return view('backend.sheriff.automations.form', ['agent' => $agent, 'models' => AiModel::orderBy('provider')->orderBy('name')->get(),
            'activities' => collect(config('deputy.activities'))->except(config('deputy.conversation_activities'))]);
    }

    private function automation(AiAgent $agent): AiAgent
    {
        abort_if($agent->talksToCustomers(), 404);

        return $agent;
    }

    private function stats($since)
    {
        return AiAutomationRun::where('started_at', '>=', $since)->selectRaw('ai_agent_id, count(*) as n, sum(checked) as checked, sum(fixed) as fixed, sum(flagged) as flagged, sum(input_tokens + output_tokens) as tokens, avg(duration_sec) as avg_sec, sum(case when status = \'failed\' then 1 else 0 end) as failed')
            ->groupBy('ai_agent_id')->get()->keyBy('ai_agent_id');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'activity' => ['required', Rule::in(array_diff(array_keys(config('deputy.activities')), config('deputy.conversation_activities')))],
            'description' => ['nullable', 'string', 'max:250'],
            'schedule' => ['required', 'string', 'max:60', fn ($a, $v, $fail) => CronExpression::isValidExpression((string) $v) || $fail('Use a cron schedule like */15 * * * * (every 15 minutes) or 0 6 * * * (6am daily).')],
            'ai_model_id' => ['required', 'exists:ai_models,id'],
            'fallback_model_id' => ['nullable', 'different:ai_model_id', 'exists:ai_models,id'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['required', 'integer', 'min:256', 'max:64000'],
            'system_prompt' => ['required', 'string', 'max:20000'],
            'active' => ['nullable', 'boolean'],
        ], ['fallback_model_id.different' => 'Pick a different model as the fallback.', 'schedule.required' => 'Automations run on a schedule. Enter one, like */15 * * * *.']);
        $data['active'] = (bool) ($data['active'] ?? false);

        return $data;
    }
}
