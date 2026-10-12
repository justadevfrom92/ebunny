<?php

namespace App\Services\Deputy;

use App\Models\AiAgent;
use App\Models\AiConversation;
use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\User;

/**
 * A live text or My Account chat conversation. The bot answers until it hands off (it can't
 * help, the model doesn't answer, or the customer asks for a person) or an agent presses
 * Take Over; from then on the agent's replies go to the customer (texts by SMS, chats in the
 * chat bubble) and the bot stays quiet until the agent hands it back.
 */
class LiveConversation
{
    public function __construct(private AgentRunner $runner) {}

    /** The customer's open chat, or a new one when they send their first message. */
    public static function chatFor(Customer $c, bool $create = false): ?AiConversation
    {
        $open = AiConversation::where('customer_id', $c->id)->where('channel', 'chat')->where('status', '!=', 'closed')
            ->where('last_message_at', '>=', now()->subMinutes(config('deputy.chat_idle_minutes')))->latest('id')->first();
        if ($open || ! $create) {
            return $open;
        }
        $agent = AiAgent::for('chat');

        return AiConversation::create(['ai_agent_id' => $agent?->id ?? AiAgent::where('activity', 'chat')->value('id'), 'ai_model_id' => $agent?->ai_model_id,
            'customer_id' => $c->id, 'channel' => 'chat', 'topic' => 'My Account chat', 'outcome' => 'resolved', 'status' => $agent ? 'bot' : 'waiting',
            'transcript' => 'Bot: Hi '.$c->first_name.', how can I help today?', 'started_at' => now(), 'last_message_at' => now()]);
    }

    /** The customer wrote: add it, and let the bot answer if it still has the conversation. */
    public function customerSays(AiConversation $conv, string $text): void
    {
        $conv->say('Customer', $text);
        if ($conv->status === 'bot') {
            preg_match('/\b(person|human|agent|representative|someone)\b/i', $text) ? $this->handOff($conv, 'Of course. I am getting a person for you now. They will see everything we covered.') : $this->botAnswers($conv, $text);
        }
    }

    private function botAnswers(AiConversation $conv, string $text): void
    {
        $agent = $conv->agent()->with(['model', 'fallback'])->first();
        if (! $agent || ! $agent->active) {
            $this->handOff($conv, 'Let me get a person to help you with that.');

            return;
        }
        // Earlier turns for the model: customer lines are the user, the bot's and people's lines are the assistant
        $history = collect($conv->lines())->slice(0, -1)->filter(fn ($l) => $l[0] !== 'System')
            ->map(fn ($l) => ['role' => $l[0] === 'Customer' ? 'user' : 'assistant', 'content' => $l[1]])->values()->all();
        $r = $this->runner->run($agent, $text, $history);
        $conv->forceFill(['ai_model_id' => $r['model']?->id ?? $conv->ai_model_id, 'input_tokens' => $conv->input_tokens + $r['input_tokens'], 'output_tokens' => $conv->output_tokens + $r['output_tokens']]);
        if (! $r['ok']) {
            $conv->say('System', 'The bot could not answer: '.$r['note']);
            $this->handOff($conv, 'Sorry, I can\'t answer that right now. I am getting a person for you.');

            return;
        }
        $reply = trim(str_replace('HANDOFF', '', $r['text']));
        if ($reply !== '') {
            $this->deliver($conv, 'Bot', $reply);
        }
        if (str_contains($r['text'], 'HANDOFF')) {
            $this->handOff($conv, $reply === '' ? 'I am getting a person for you now.' : null);
        }
    }

    /** The bot gives the conversation to a person: it waits in Needs a Person until someone takes it. */
    public function handOff(AiConversation $conv, ?string $message = null): void
    {
        if ($message) {
            $this->deliver($conv, 'Bot', $message);
        }
        $conv->forceFill(['status' => 'waiting', 'outcome' => 'handed_off'])->save();
    }

    public function takeOver(AiConversation $conv, User $user): void
    {
        abort_unless($conv->isLive(), 422, 'This conversation is closed.');
        if ($conv->status === 'agent' && $conv->handed_to_id === $user->id) {
            return;
        }
        $conv->forceFill(['status' => 'agent', 'outcome' => 'handed_off', 'handed_to_id' => $user->id])->save();
        $conv->say('System', $user->name.' took over.');
    }

    /** An agent's reply. Taking over first if the bot still had it. Texts go out by SMS. */
    public function agentSays(AiConversation $conv, User $user, string $text): string
    {
        $this->takeOver($conv, $user);

        return $this->deliver($conv, AiConversation::speakerFor($user), $text, $user);
    }

    public function handBack(AiConversation $conv, User $user): void
    {
        $conv->forceFill(['status' => 'bot'])->save();
        $conv->say('System', $user->name.' handed the conversation back to the bot.');
    }

    public function close(AiConversation $conv, User $user): void
    {
        $conv->say('System', 'Closed by '.$user->name.'.');
        $conv->forceFill(['status' => 'closed'])->save();
    }

    /** The agent calls the customer: logged on the conversation and as a note on the account. */
    public function call(AiConversation $conv, User $user): string
    {
        $c = $conv->customer;
        abort_if(! $c || blank($c->phone), 422, 'This customer has no phone number.');
        $this->takeOver($conv, $user);
        $conv->say('System', $user->name.' called the customer at '.$c->phone.'.');
        $c->notes()->create(['user_id' => $user->id, 'author' => $user->name, 'disposition' => 'Outbound Call', 'category' => 'Customer Service', 'action' => 'Outbound Call',
            'priority' => 'Low', 'body' => 'Called '.$c->phone.' from Deputy '.strtolower(config('deputy.channels.'.$conv->channel)).' conversation #'.$conv->id.'.']);

        return $c->phone;
    }

    /** Adds the line and, for texts, sends it to the customer's phone. */
    private function deliver(AiConversation $conv, string $who, string $text, ?User $user = null): string
    {
        $conv->say($who, $text);
        if ($conv->channel !== 'sms' || ! $conv->customer || blank($conv->customer->phone)) {
            return 'Sent.';
        }
        $ready = collect(config('admin.integrations.sms.env'))->every(fn ($v) => filled($v));
        ContactLog::create(['customer_id' => $conv->customer_id, 'channel' => 'SMS', 'template' => $user ? 'Agent text' : 'Bot text', 'body' => mb_substr($text, 0, 480),
            'phone' => $conv->customer->phone, 'status' => $ready ? 'queued' : 'not sent', 'user_id' => $user?->id]);

        return $ready ? 'Text queued for Twilio.' : 'Text logged but not sent: Twilio is not configured.';
    }
}
