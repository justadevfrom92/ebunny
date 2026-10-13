<?php

namespace Tests\Feature;

use App\Models\AiAgent;
use App\Models\AiConversation;
use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Deputy: texts and My Account chats only, a person taking over (reply, call, hand back), the chat bubble, and automations. */
class DeputyLiveTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    public function test_conversations_are_only_texts_and_chats_and_ai_ops_only_sees_deputy(): void
    {
        $this->assertSame([], AiConversation::whereNotIn('channel', ['sms', 'chat'])->pluck('channel')->unique()->values()->all());
        $this->assertFalse(AiAgent::whereIn('activity', ['phone', 'email'])->exists());

        $ai = User::where('email', 'ai@example.com')->firstOrFail();
        $this->actingAs($ai);
        $this->get(route('deputy.conversations'))->assertOk()->assertSee('Needs a Person')->assertSee('Live Now')->assertSee('Take Over');
        // Scheduled automations live in Sheriff, not Deputy
        $this->get('/admin/deputy/automations')->assertNotFound();
        $this->get(route('deputy.agents'))->assertOk()->assertDontSee('Enrollment Fixer');
        $this->get('/admin/walker')->assertRedirect();
        $this->get('/admin')->assertOk()->assertDontSee('Walker');
    }

    public function test_agent_takes_over_replies_by_text_calls_and_hands_back(): void
    {
        $this->actingAs($this->admin());
        $conv = AiConversation::where('channel', 'sms')->where('status', 'waiting')->firstOrFail();
        $this->get(route('deputy.conversations.show', $conv))->assertOk()->assertSee('Take Over')->assertSee('Text the customer')->assertSee('Call '.$conv->customer->phone);

        // Replying takes it over and texts the customer
        $this->post(route('deputy.conversations.reply', $conv), ['message' => 'Hi, I can set that up for you.'])->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, 'Text'));
        $conv->refresh();
        $this->assertSame(['agent', $this->admin()->id], [$conv->status, $conv->handed_to_id]);
        $this->assertStringContainsString('Admin Demo (agent): Hi, I can set that up for you.', $conv->transcript);
        $this->assertTrue(ContactLog::where('customer_id', $conv->customer_id)->where('channel', 'SMS')->where('body', 'Hi, I can set that up for you.')->exists());
        $this->get(route('deputy.conversations.show', ['conversation' => $conv, 'part' => 'live']))->assertOk()->assertSee('Hand Back to Bot')->assertDontSee('<html', false);

        // Call: logged on the conversation and as a note on the account, and the page dials
        $this->post(route('deputy.conversations.call', $conv))->assertRedirect()->assertSessionHas('dial');
        $this->assertStringContainsString('called the customer', $conv->fresh()->transcript);
        $this->assertTrue($conv->customer->notes()->where('disposition', 'Outbound Call')->exists());

        $this->post(route('deputy.conversations.handback', $conv))->assertRedirect();
        $this->assertSame('bot', $conv->fresh()->status);
        $this->post(route('deputy.conversations.close', $conv))->assertRedirect(route('deputy.conversations'));
        $this->assertSame('closed', $conv->fresh()->status);
        $this->post(route('deputy.conversations.reply', $conv), ['message' => 'late'])->assertStatus(422);
    }

    public function test_my_account_chat_bubble_bot_handoff_and_agent_reply(): void
    {
        $c = Customer::where('username', 'test.business')->firstOrFail();
        $this->post(route('myaccount.login.test', $c));
        $this->get(route('myaccount.dashboard'))->assertOk()->assertSee('Chat with us')->assertSee('Talk to a person');
        $this->getJson(route('myaccount.chat'))->assertOk()->assertJsonPath('status', 'new');

        // The bot answers through its model
        config(['admin.integrations.open_models.env' => ['OPEN_MODELS_URL' => 'https://open.test/v1', 'OPEN_MODELS_API_KEY' => 'k']]);
        Http::fake(['open.test/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'Your bill is due on the 5th.'], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5]])]);
        $this->postJson(route('myaccount.chat.send'), ['message' => 'When is my bill due?'])->assertOk()->assertJsonPath('status', 'bot')
            ->assertJsonPath('messages.1', ['from' => 'me', 'text' => 'When is my bill due?'])->assertJsonPath('messages.2.text', 'Your bill is due on the 5th.');

        // Asking for a person hands it off; it shows in Needs a Person
        $this->postJson(route('myaccount.chat.person'))->assertJsonPath('status', 'waiting');
        $conv = AiConversation::where('customer_id', $c->id)->where('channel', 'chat')->latest('id')->firstOrFail();
        $this->actingAs($this->admin(), 'web')->get(route('deputy.conversations'))->assertSee(route('deputy.conversations.show', $conv));
        $this->post(route('deputy.conversations.reply', $conv), ['message' => 'Hi, this is Admin.'])->assertRedirect();

        // The customer sees the person, and the bot stays quiet
        $state = $this->getJson(route('myaccount.chat'))->assertJsonPath('status', 'agent')->json('messages');
        $this->assertSame(['from' => 'person', 'name' => 'Admin', 'text' => 'Hi, this is Admin.'], end($state));
        $this->postJson(route('myaccount.chat.send'), ['message' => 'Thanks'])->assertOk();
        Http::assertSentCount(1);

        // No model set up: the bot hands the chat to a person instead of going quiet
        $other = Customer::where('username', 'test.credit')->firstOrFail();
        config(['admin.integrations.open_models.env.OPEN_MODELS_URL' => null, 'admin.integrations.local_models.env.LOCAL_MODELS_URL' => null]);
        $this->post(route('myaccount.login.test', $other));
        $this->postJson(route('myaccount.chat.send'), ['message' => 'Hello?'])->assertJsonPath('status', 'waiting');
    }

    public function test_deputy_agents_only_talk_to_customers(): void
    {
        $this->actingAs($this->admin());
        $model = AiAgent::firstOrFail()->ai_model_id;
        $base = ['name' => 'Fixer', 'ai_model_id' => $model, 'temperature' => 0, 'max_tokens' => 500, 'system_prompt' => 'Fix things.', 'active' => 1];
        $this->get(route('deputy.agents.create'))->assertOk()->assertDontSee('Automation: runs on a schedule')->assertDontSee('<select id="activity"', false);
        // Scheduled activities are added in Sheriff → Cron Automations
        $this->post(route('deputy.agents.store'), $base + ['activity' => 'payments', 'schedule' => '*/10 * * * *'])->assertSessionHasErrors('activity');
        $this->post(route('deputy.agents.store'), ['name' => 'Chatty', 'activity' => 'chat', 'schedule' => '* * * * *'] + $base)->assertRedirect();
        $this->assertNull(AiAgent::where('name', 'Chatty')->value('schedule'));
    }
}
