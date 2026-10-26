<?php

namespace Tests\Feature;

use App\Models\AiAgent;
use App\Models\AiConversation;
use App\Models\AiModel;
use App\Models\Customer;
use App\Models\IntegrationTest;
use App\Models\Note;
use App\Models\TableSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Test sign-ins, API Configure (upload .env, test connection), the Deputy AI agents app, and cached tables. */
class DeputyAndConfigTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    public function test_test_sign_ins_list_every_sample_admin_and_switch(): void
    {
        $this->get(route('admin.login'))->assertOk()->assertSee('Test Sign-Ins')->assertSee('AI Ops Demo')->assertSee('CS Lead Demo');
        $ai = User::where('email', 'ai@example.com')->firstOrFail();
        $this->post(route('admin.login.test', $ai))->assertRedirect(route('admin.launcher'));
        $this->assertAuthenticatedAs($ai);
        $this->get('/admin')->assertOk()->assertSee('Deputy');
        $this->get(route('admin.settings'))->assertOk()->assertSee('Switch User');   // switching lives in Settings
        $this->get('/admin/corral')->assertRedirect();   // no Corral access for this role

        // Switch to another sample admin from Settings
        $csr = User::where('email', 'csr@example.com')->firstOrFail();
        $this->post(route('admin.login.test', $csr))->assertRedirect();
        $this->assertAuthenticatedAs($csr);
        // Inactive or non-sample users can't be used
        $this->post(route('admin.login.test', User::where('active', false)->firstOrFail()))->assertNotFound();

        config(['admin.test_logins' => false]);
        $this->post(route('admin.login.test', $ai))->assertNotFound();
        auth()->logout();
        $this->get(route('admin.login'))->assertOk()->assertDontSee('Test Sign-Ins');
    }

    public function test_configure_page_uploads_env_and_tests_the_connection(): void
    {
        $env = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($env, "APP_NAME=Test\nSTRIPE_KEY=old\n");
        config(['admin.env_path' => $env]);
        $this->actingAs($this->admin());

        $this->get(route('sheriff.integrations.index'))->assertOk()->assertSee('Configure');
        $this->get(route('sheriff.integrations.configure', 'stripe'))->assertOk()->assertSee('Upload .env')->assertSee('Test Connection')->assertSee('STRIPE_SECRET');
        $this->get(route('sheriff.integrations.configure', 'nope'))->assertNotFound();

        $file = UploadedFile::fake()->createWithContent('.env', "# keys\nSTRIPE_KEY=pk_test_abc\nexport STRIPE_SECRET=\"sk_test 123\"\nSTRIPE_WEBHOOK_SECRET=whsec_x # inline\nOTHER_SECRET=nope\n");
        $this->post(route('sheriff.integrations.upload', 'stripe'), ['env_file' => $file])->assertRedirect()->assertSessionHas('status');
        $text = file_get_contents($env);
        $this->assertStringContainsString("STRIPE_KEY=pk_test_abc\n", $text);
        $this->assertStringContainsString('STRIPE_SECRET="sk_test 123"', $text);
        $this->assertStringContainsString('STRIPE_WEBHOOK_SECRET=whsec_x', $text);
        $this->assertStringNotContainsString('OTHER_SECRET', $text);
        $this->assertStringContainsString('APP_NAME=Test', $text);
        $this->assertSame(1, substr_count($text, 'STRIPE_KEY='));
        // The secret value never appears on the page
        $this->get(route('sheriff.integrations.configure', 'stripe'))->assertDontSee('sk_test');

        // A file with none of this company's keys is refused
        $this->post(route('sheriff.integrations.upload', 'stripe'), ['env_file' => UploadedFile::fake()->createWithContent('x.env', "FOO=bar\n")])->assertSessionHasErrors('env_file');

        // Without the right, no upload
        $csr = User::where('email', 'csr@example.com')->firstOrFail();
        $this->assertFalse($csr->can('configure'));

        // Test Connection: missing settings, then a real (faked) call
        $before = IntegrationTest::where('integration', 'stripe')->count();   // the sample data has a few
        $this->post(route('sheriff.integrations.test', 'stripe'))->assertRedirect()->assertSessionHas('test_result', fn ($r) => ! $r['ok'] && str_contains($r['message'], 'Not set'));
        config(['admin.integrations.stripe.env' => ['STRIPE_KEY' => 'pk', 'STRIPE_SECRET' => 'sk', 'STRIPE_WEBHOOK_SECRET' => 'wh']]);
        Http::fake(['api.stripe.com/*' => Http::sequence()->push(['object' => 'balance'], 200)->push(['error' => ['message' => 'Invalid API Key']], 401)]);
        $this->post(route('sheriff.integrations.test', 'stripe'))->assertSessionHas('test_result', fn ($r) => $r['ok']);
        $this->post(route('sheriff.integrations.test', 'stripe'))->assertSessionHas('test_result', fn ($r) => ! $r['ok'] && str_contains($r['message'], 'Rejected'));
        $this->assertSame($before + 3, IntegrationTest::where('integration', 'stripe')->count());
        $this->get(route('sheriff.integrations.configure', 'stripe'))->assertSee('Recent Tests')->assertSee('Rejected');
        @unlink($env);
    }

    public function test_deputy_pages_and_agent_test_with_fallback(): void
    {
        $this->actingAs($this->admin());
        $c = AiConversation::whereNotNull('customer_id')->firstOrFail();
        $this->get(route('deputy.dashboard'))->assertOk()->assertSee('Model by Agent')->assertSee('Kimi K2 Instruct')->assertSee('Qwen3 8B')->assertDontSee('claude');
        $this->get(route('deputy.conversations'))->assertOk()->assertSee('Search Conversations');
        $this->get(route('deputy.conversations', ['outcome' => 'handed_off', 'channel' => 'sms']))->assertOk();
        $this->get(route('deputy.conversations.show', $c))->assertOk()->assertSee('Transcript')->assertSee($c->customer->account);
        $this->get(route('deputy.agents'))->assertOk()->assertSee('Text Responder');
        $this->get(route('deputy.agents.create'))->assertOk()->assertSee('Temperature');
        $this->get(route('deputy.models'))->assertOk()->assertSee('qwen3:8b')->assertSee('moonshotai/Kimi-K2-Instruct');
        $this->get(route('deputy.models.create'))->assertOk();

        // Add a downloaded model and a hosted one, and an agent on the hosted model with the local one as fallback
        $this->post(route('deputy.models.store'), ['name' => 'Phi 3', 'provider' => 'local', 'model_id' => 'phi3:mini', 'size_gb' => 2.2])->assertRedirect(route('deputy.models'));
        $phi = AiModel::where('model_id', 'phi3:mini')->firstOrFail();
        $this->assertSame('missing', $phi->status);
        $this->post(route('deputy.models.store'), ['name' => 'Bad', 'provider' => 'local', 'model_id' => 'has spaces'])->assertSessionHasErrors('model_id');
        $this->post(route('deputy.models.store'), ['name' => 'Bad', 'provider' => 'anthropic', 'model_id' => 'x'])->assertSessionHasErrors('provider');
        $kimi = AiModel::where('model_id', 'moonshotai/Kimi-K2-Instruct')->firstOrFail();
        $this->post(route('deputy.agents.store'), ['name' => 'Tester', 'activity' => 'chat', 'ai_model_id' => $kimi->id, 'fallback_model_id' => $phi->id,
            'temperature' => 0.2, 'max_tokens' => 1000, 'system_prompt' => 'Be brief.', 'active' => 1])->assertRedirect(route('deputy.agents'));
        $agent = AiAgent::where('name', 'Tester')->firstOrFail();

        // The hosted API answers through its OpenAI-compatible chat completions endpoint
        config(['admin.integrations.open_models.env' => ['OPEN_MODELS_URL' => 'https://open.test/v1', 'OPEN_MODELS_API_KEY' => 'key-123'],
            'admin.integrations.local_models.env.LOCAL_MODELS_URL' => 'http://models.test:11434']);
        Http::fake([
            'open.test/v1/chat/completions' => Http::sequence()
                ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Kimi says hi.'], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 30, 'completion_tokens' => 4]])
                ->push(['error' => ['message' => 'model overloaded']], 503),
            'open.test/v1/models' => Http::response(['data' => [['id' => 'moonshotai/Kimi-K2-Instruct'], ['id' => 'Qwen/Qwen3-235B-A22B-Instruct-2507']]]),
            'models.test:11434/api/chat' => Http::response(['message' => ['role' => 'assistant', 'content' => 'Your bill went up with the heat.'], 'prompt_eval_count' => 42, 'eval_count' => 9, 'done_reason' => 'stop']),
            'models.test:11434/api/tags' => Http::response(['models' => [['name' => 'phi3:mini']]]),
        ]);
        $this->get(route('deputy.agents.test', $agent))->assertOk()->assertSee('Send to Agent');
        $this->post(route('deputy.agents.test.run', $agent), ['message' => 'Hello'])
            ->assertSessionHas('agent_result', fn ($r) => $r['ok'] && $r['text'] === 'Kimi says hi.' && $r['model_name'] === $kimi->name && $r['input_tokens'] === 30);
        Http::assertSent(fn ($req) => str_ends_with($req->url(), '/chat/completions') && $req['model'] === 'moonshotai/Kimi-K2-Instruct'
            && $req['temperature'] == 0.2 && $req['messages'][0]['role'] === 'system' && $req->hasHeader('Authorization', 'Bearer key-123'));

        // The hosted model errors, so the fallback (the downloaded model) answers
        $this->post(route('deputy.agents.test.run', $agent), ['message' => 'Why is my bill high?'])
            ->assertSessionHas('agent_result', fn ($r) => $r['ok'] && $r['text'] === 'Your bill went up with the heat.' && str_contains($r['note'], 'fallback') && str_contains($r['note'], '503'));
        $test = AiConversation::where('ai_agent_id', $agent->id)->where('channel', 'test')->latest('id')->firstOrFail();
        $this->assertSame($phi->id, $test->ai_model_id);
        $this->assertSame(42, $test->input_tokens);

        // Check finds each model on its server
        $this->post(route('deputy.models.check', $phi))->assertRedirect();
        $this->assertSame('available', $phi->fresh()->status);
        $this->post(route('deputy.models.check', $kimi))->assertRedirect();
        $this->assertSame('available', $kimi->fresh()->status);
        $this->post(route('sheriff.integrations.test', 'open_models'))->assertSessionHas('test_result', fn ($r) => $r['ok'] && str_contains($r['message'], '2 models'));

        // Neither model can answer: the test is saved as failed
        config(['admin.integrations.open_models.env.OPEN_MODELS_URL' => null, 'admin.integrations.local_models.env.LOCAL_MODELS_URL' => null]);
        $this->post(route('deputy.agents.test.run', $agent), ['message' => 'Hello'])->assertSessionHas('agent_result', fn ($r) => ! $r['ok'] && str_contains($r['note'], 'OPEN_MODELS_URL'));
        $this->assertSame('failed', AiConversation::where('ai_agent_id', $agent->id)->latest('id')->first()->outcome);
    }

    public function test_my_account_test_sign_ins(): void
    {
        $page = $this->get(route('myaccount.login'))->assertOk()->assertSee('Test Sign-Ins');
        $business = Customer::where('username', 'test.business')->firstOrFail();
        $page->assertSee($business->name);
        $this->post(route('myaccount.login.test', $business))->assertRedirect(route('myaccount.dashboard'));
        $this->assertAuthenticatedAs($business, 'customer');
        $this->get(route('myaccount.dashboard'))->assertOk()->assertSee('Testing: switch customer');

        // Switch to another sample customer from inside My Account
        $credit = Customer::where('username', 'test.credit')->firstOrFail();
        $this->post(route('myaccount.login.test', $credit))->assertRedirect(route('myaccount.dashboard'));
        $this->assertAuthenticatedAs($credit, 'customer');

        // Only customers with a My Account login, and never with test sign-ins off
        $this->post(route('myaccount.login.test', Customer::whereNull('password')->firstOrFail()))->assertNotFound();
        config(['admin.test_logins' => false]);
        $this->post(route('myaccount.login.test', $business))->assertNotFound();
        auth('customer')->logout();
        $this->get(route('myaccount.login'))->assertOk()->assertDontSee('Test Sign-Ins');
    }

    public function test_cached_tables_for_pages_and_new_records(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('deputy.agents'))->assertOk();
        $snap = TableSnapshot::where('kind', 'page')->where('key', '/admin/deputy/agents')->firstOrFail();
        $this->assertSame('deputy', $snap->app);
        $this->assertStringContainsString('Text Responder', $snap->html);
        $this->assertStringNotContainsString('<a ', $snap->html);
        $this->assertStringNotContainsString('<form', $snap->html);
        // Unchanged page: no second copy
        $this->get(route('deputy.agents'));
        $this->assertSame(1, TableSnapshot::where('key', '/admin/deputy/agents')->count());

        // Adding a record keeps a copy of its table with the new row
        $c = Customer::firstOrFail();
        $this->post(route('corral.customers.notes', $c), ['body' => 'Cached table check'])->assertSessionHasNoErrors();
        $note = Note::where('body', 'Cached table check')->firstOrFail();
        $rec = TableSnapshot::where('kind', 'record')->where('key', 'notes')->latest('id')->firstOrFail();
        $this->assertSame($note->id, (int) $rec->record_id);
        $this->assertContains('body', $rec->columns);
        $this->post(route('deputy.models.store'), ['name' => 'Gemma', 'provider' => 'local', 'model_id' => 'gemma2:9b']);
        $rec = TableSnapshot::where('kind', 'record')->where('key', 'ai_models')->latest('id')->firstOrFail();
        $this->assertSame('gemma2:9b', collect($rec->rows)->firstWhere('id', $rec->record_id)['model_id']);

        $this->get(route('sheriff.cache'))->assertOk()->assertSee('/admin/deputy/agents');
        $this->get(route('sheriff.cache', ['kind' => 'record']))->assertOk()->assertSee('ai_models');
        $this->get(route('sheriff.cache.show', $snap))->assertOk()->assertSee('Text Responder');
        $this->get(route('sheriff.cache.show', $rec))->assertOk()->assertSee('gemma2:9b');
        // Users table copies never include passwords
        User::create(['name' => 'New Person', 'email' => 'new@example.com', 'password' => 'secret-pass', 'role_id' => $this->admin()->role_id, 'active' => true]);
        $u = TableSnapshot::where('kind', 'record')->where('key', 'users')->latest('id')->firstOrFail();
        $this->assertNotContains('password', $u->columns);
        $this->assertNotContains('remember_token', $u->columns);
    }
}
