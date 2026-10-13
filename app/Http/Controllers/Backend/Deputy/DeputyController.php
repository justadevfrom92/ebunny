<?php

namespace App\Http\Controllers\Backend\Deputy;

use App\Http\Controllers\Controller;
use App\Models\AiAgent;
use App\Models\AiConversation;
use App\Models\AiModel;
use App\Services\Deputy\AgentRunner;
use App\Services\Deputy\LiveConversation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Deputy — AI agents on open-weight models. Which model each agent runs on (downloaded to
 * the local server or hosted behind an OpenAI-compatible API); conversations with customers by
 * text and in the My Account chat, where a person can take over, reply or call (scheduled automations are in Sheriff)
 * that run on a schedule to fix issues; and a place to test an agent.
 */
class DeputyController extends Controller
{
    public function dashboard(): View
    {
        $since = today()->subDays(29);
        $stats = fn ($q) => $q->where('started_at', '>=', $since)->whereIn('channel', ['sms', 'chat']);
        $byAgent = $stats(AiConversation::query())->selectRaw("ai_agent_id, count(*) as n, sum(input_tokens + output_tokens) as tokens, avg(duration_sec) as avg_sec,
                sum(case when outcome = 'resolved' then 1 else 0 end) as resolved, sum(case when outcome = 'handed_off' then 1 else 0 end) as handed")
            ->groupBy('ai_agent_id')->get()->keyBy('ai_agent_id');
        $byModel = $stats(AiConversation::query())->selectRaw('ai_model_id, count(*) as n, sum(input_tokens) as input, sum(output_tokens) as output')->groupBy('ai_model_id')->get()->keyBy('ai_model_id');
        $total = $byAgent->sum('n');

        return view('backend.deputy.dashboard', [
            'agents' => AiAgent::talking()->with(['model', 'fallback'])->orderByDesc('active')->orderBy('name')->get(),
            'models' => AiModel::withCount('agents')->orderBy('provider')->orderBy('name')->get(),
            'byAgent' => $byAgent,
            'byModel' => $byModel,
            'total' => $total,
            'resolvedRate' => $total ? round(100 * $byAgent->sum('resolved') / $total) : 0,
            'handed' => $byAgent->sum('handed'),
            'tokens' => $byAgent->sum('tokens'),
            'local' => AiModel::where('provider', 'local')->count(),
            'ready' => ['hosted' => filled(config('admin.integrations.open_models.env.OPEN_MODELS_URL')), 'local' => filled(config('admin.integrations.local_models.env.LOCAL_MODELS_URL'))],
            'live' => AiConversation::whereIn('status', ['waiting', 'agent', 'bot'])->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'recent' => AiConversation::with(['agent', 'model', 'customer'])->whereIn('channel', ['sms', 'chat'])->latest('started_at')->limit(8)->get(),
        ]);
    }

    // ---------- Conversations ----------

    /**
     * Conversations: texts and My Account chats. Live ones sit on top in their own tables (waiting
     * for a person, then being answered); the search below covers every conversation.
     */
    public function conversations(Request $request): View
    {
        $f = $request->only(['start', 'end', 'account', 'agent', 'model', 'channel', 'outcome', 'topic']);
        $q = AiConversation::with(['agent', 'model', 'customer', 'handedTo', 'user'])
            ->when(($f['channel'] ?? null) === 'test', fn ($q) => $q->where('channel', 'test'), fn ($q) => $q->whereIn('channel', ['sms', 'chat']))
            ->when($f['start'] ?? null, fn ($q, $d) => $q->whereDate('started_at', '>=', $d))
            ->when($f['end'] ?? null, fn ($q, $d) => $q->whereDate('started_at', '<=', $d))
            ->when($f['account'] ?? null, fn ($q, $a) => $q->whereHas('customer', fn ($c) => $c->where('account', 'like', trim($a).'%')))
            ->when($f['agent'] ?? null, fn ($q, $a) => $q->where('ai_agent_id', $a))
            ->when($f['model'] ?? null, fn ($q, $m) => $q->where('ai_model_id', $m))
            ->when(in_array($f['channel'] ?? null, ['sms', 'chat'], true), fn ($q) => $q->where('channel', $f['channel']))
            ->when($f['outcome'] ?? null, fn ($q, $o) => $q->where('outcome', $o))
            ->when($f['topic'] ?? null, fn ($q, $t) => $q->where('topic', $t));
        $live = fn (array $statuses) => AiConversation::with(['agent', 'customer', 'handedTo'])->whereIn('status', $statuses)->oldest('last_message_at')->get();

        return view('backend.deputy.conversations', [
            'f' => $f,
            'waiting' => $live(['waiting']),
            'answering' => $live(['agent', 'bot']),
            'conversations' => $q->latest('started_at')->latest('id')->paginate(50)->withQueryString(),
            'agents' => AiAgent::whereIn('activity', config('deputy.conversation_activities'))->orderBy('name')->get(['id', 'name']),
            'models' => AiModel::orderBy('name')->get(['id', 'name']),
            'topics' => AiConversation::whereIn('channel', ['sms', 'chat'])->distinct()->orderBy('topic')->pluck('topic'),
        ]);
    }

    /** One conversation. While it's live the page asks for ?part=live every few seconds and swaps in new messages. */
    public function conversation(Request $request, AiConversation $conversation): View
    {
        $c = $conversation->load(['agent', 'model', 'customer', 'handedTo', 'user']);

        return view($request->query('part') === 'live' ? 'backend.deputy._live' : 'backend.deputy.conversation', ['c' => $c]);
    }

    public function takeOver(Request $request, AiConversation $conversation, LiveConversation $live): RedirectResponse
    {
        $live->takeOver($conversation, $request->user());

        return redirect()->route('deputy.conversations.show', $conversation)->with('status', 'You have this conversation. The bot will stay quiet until you hand it back.');
    }

    public function reply(Request $request, AiConversation $conversation, LiveConversation $live): RedirectResponse
    {
        abort_unless($conversation->isLive(), 422, 'This conversation is closed.');
        $data = $request->validate(['message' => ['required', 'string', 'max:'.($conversation->channel === 'sms' ? 480 : 2000)]]);
        $message = $live->agentSays($conversation, $request->user(), $data['message']);

        return redirect()->route('deputy.conversations.show', $conversation)->with('status', $message);
    }

    public function handBack(Request $request, AiConversation $conversation, LiveConversation $live): RedirectResponse
    {
        abort_unless($conversation->isLive(), 422, 'This conversation is closed.');
        $live->handBack($conversation, $request->user());

        return redirect()->route('deputy.conversations.show', $conversation)->with('status', 'The bot is answering again.');
    }

    public function close(Request $request, AiConversation $conversation, LiveConversation $live): RedirectResponse
    {
        abort_unless($conversation->isLive(), 422, 'This conversation is already closed.');
        $live->close($conversation, $request->user());

        return redirect()->route('deputy.conversations')->with('status', 'Conversation #'.$conversation->id.' closed.');
    }

    /** Call the customer: logged here and on the account, then the browser dials the number. */
    public function call(Request $request, AiConversation $conversation, LiveConversation $live): RedirectResponse
    {
        $phone = $live->call($conversation, $request->user());

        return redirect()->route('deputy.conversations.show', $conversation)->with('status', 'Calling '.$phone.'.')->with('dial', preg_replace('/[^\d+]/', '', $phone));
    }

    // ---------- Automations ----------

    /** Agents that run on a schedule to fix issues in the background, and what each run did. */
    // ---------- Agents ----------

    public function agents(): View
    {
        return view('backend.deputy.agents', ['agents' => AiAgent::talking()->with(['model', 'fallback'])->withCount(['conversations' => fn ($q) => $q->where('channel', '!=', 'test')])->withMax('conversations', 'started_at')->orderByDesc('active')->orderBy('name')->get()]);
    }

    public function agentForm(?AiAgent $agent = null): View
    {
        abort_if($agent && ! $agent->talksToCustomers(), 404);   // scheduled automations are edited in Sheriff

        return view('backend.deputy.agent-form', [
            'agent' => $agent ?? new AiAgent(['temperature' => 0.3, 'max_tokens' => 2000, 'active' => true]),
            'models' => AiModel::orderBy('provider')->orderBy('name')->get(),
        ]);
    }

    public function saveAgent(Request $request, ?AiAgent $agent = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'activity' => ['required', Rule::in(config('deputy.conversation_activities'))],
            'description' => ['nullable', 'string', 'max:250'],
            'ai_model_id' => ['required', 'exists:ai_models,id'],
            'fallback_model_id' => ['nullable', 'different:ai_model_id', 'exists:ai_models,id'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['required', 'integer', 'min:256', 'max:64000'],
            'system_prompt' => ['required', 'string', 'max:20000'],
            'active' => ['nullable', 'boolean'],
        ], ['fallback_model_id.different' => 'Pick a different model as the fallback.']);
        $data['active'] = (bool) ($data['active'] ?? false);
        // Agents here talk to customers and answer as messages come in; scheduled automations are in Sheriff → Cron Automations
        abort_if($agent && ! $agent->talksToCustomers(), 404);
        $data['schedule'] = null;
        $agent ? $agent->update($data) : AiAgent::create($data);

        return redirect()->route('deputy.agents')->with('status', 'Agent saved');
    }

    /** Test an agent: send it a message and see what its model answers. Saved as a Test conversation. */
    public function testForm(AiAgent $agent): View
    {
        abort_unless($agent->talksToCustomers(), 404);

        return view('backend.deputy.agent-test', ['agent' => $agent->load(['model', 'fallback']),
            'tests' => $agent->conversations()->with('model', 'user')->where('channel', 'test')->latest('started_at')->limit(10)->get()]);
    }

    public function runTest(Request $request, AiAgent $agent, AgentRunner $runner): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:4000']]);
        $r = $runner->run($agent->load(['model', 'fallback']), $data['message']);
        $conversation = AiConversation::create(['ai_agent_id' => $agent->id, 'ai_model_id' => $r['model']?->id, 'channel' => 'test', 'topic' => 'Test',
            'outcome' => $r['ok'] ? 'resolved' : 'failed', 'user_id' => $request->user()->id, 'started_at' => now(), 'duration_sec' => (int) ceil($r['ms'] / 1000),
            'input_tokens' => $r['input_tokens'], 'output_tokens' => $r['output_tokens'],
            'transcript' => 'Tester: '.str_replace("\n", ' ', $data['message'])."\n".($r['ok'] ? 'Agent: '.str_replace("\n", ' ', $r['text']) : 'System: No answer. '.$r['note'])]);

        return redirect()->route('deputy.agents.test', $agent)->with('agent_result', $r + ['conversation' => $conversation->id, 'model_name' => $r['model']?->name])->withInput();
    }

    // ---------- Models ----------

    public function models(): View
    {
        return view('backend.deputy.models', ['models' => AiModel::withCount(['agents', 'conversations'])->orderBy('provider')->orderBy('name')->get(),
            'usedAsFallback' => AiAgent::whereNotNull('fallback_model_id')->selectRaw('fallback_model_id, count(*) as n')->groupBy('fallback_model_id')->pluck('n', 'fallback_model_id')]);
    }

    public function modelForm(?AiModel $model = null): View
    {
        return view('backend.deputy.model-form', ['model' => $model ?? new AiModel(['provider' => 'local', 'status' => 'available'])]);
    }

    public function saveModel(Request $request, ?AiModel $model = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'provider' => ['required', Rule::in(array_keys(config('deputy.providers')))],
            'model_id' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._:\/\-]+$/', Rule::unique('ai_models')->where('provider', $request->input('provider'))->ignore($model)],
            'source' => ['nullable', 'string', 'max:250'],
            'size_gb' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'quantization' => ['nullable', 'string', 'max:20'],
            'context_window' => ['nullable', 'integer', 'min:512', 'max:10000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], ['model_id.regex' => 'Use the model\'s id as the server knows it, like qwen3:8b or moonshotai/Kimi-K2-Instruct.']);
        $model ? $model->update($data) : $model = AiModel::create($data + ['status' => 'missing']);

        return redirect()->route('deputy.models')->with('status', $model->name.' saved. Use Check to confirm it is available.');
    }

    /** Is the model there? Downloaded: the local server's list of models. Hosted: the API's /models list. */
    public function checkModel(AiModel $model): RedirectResponse
    {
        [$status, $message] = $model->isLocal() ? $this->checkLocal($model) : $this->checkHosted($model);
        $model->update(['status' => $status, 'checked_at' => now()]);

        return back()->with('status', $model->name.': '.$message);
    }

    private function checkHosted(AiModel $model): array
    {
        $url = (string) config('admin.integrations.open_models.env.OPEN_MODELS_URL');
        if ($url === '') {
            return [$model->status, 'OPEN_MODELS_URL is not set, so it can\'t be checked (Sheriff → APIs → Open Models API → Configure).'];
        }
        try {
            $r = Http::timeout(10)->acceptJson()->withToken((string) config('admin.integrations.open_models.env.OPEN_MODELS_API_KEY'))->get(rtrim($url, '/').'/models');
        } catch (ConnectionException) {
            return [$model->status, 'could not reach the open models API.'];
        }
        if (! $r->successful()) {
            return [$model->status, 'the open models API answered HTTP '.$r->status().'.'];
        }

        return collect($r->json('data', []))->pluck('id')->contains($model->model_id)
            ? ['available', 'served by the open models API.']
            : ['missing', 'the open models API doesn\'t list '.$model->model_id.'. Check the id against the provider\'s model list.'];
    }

    private function checkLocal(AiModel $model): array
    {
        $url = (string) config('admin.integrations.local_models.env.LOCAL_MODELS_URL');
        if ($url === '') {
            return [$model->status, 'LOCAL_MODELS_URL is not set, so it can\'t be checked (Sheriff → APIs → Local Models → Configure).'];
        }
        try {
            $names = collect(Http::timeout(10)->get(rtrim($url, '/').'/api/tags')->json('models', []))->pluck('name');
        } catch (ConnectionException) {
            return [$model->status, 'could not reach the local model server.'];
        }
        $found = $names->contains($model->model_id) || $names->contains($model->model_id.':latest');

        return $found ? ['available', 'downloaded and ready on the local server.'] : ['missing', 'not on the local server. Download it there (for Ollama: ollama pull '.$model->model_id.').'];
    }
}
