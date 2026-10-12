<?php

namespace Database\Seeders;

use App\Models\AiAgent;
use App\Models\AiAutomationRun;
use App\Models\AiConversation;
use App\Models\AiModel;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Deputy: open-weight models (hosted and downloaded), the agents on them, and 30 days of (fictional) text and chat conversations, and automation runs. */
class DeputySeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(7);
        $m = [];
        foreach ([
            // Hosted open-weight models, through the OpenAI-compatible open models API
            ['kimi', 'Kimi K2 Instruct', 'hosted', 'moonshotai/Kimi-K2-Instruct', 'Moonshot AI, open weights on Hugging Face', null, null, 131072, 'available'],
            ['qwen235', 'Qwen3 235B A22B', 'hosted', 'Qwen/Qwen3-235B-A22B-Instruct-2507', 'Alibaba Qwen, open weights on Hugging Face', null, null, 262144, 'available'],
            ['deepseek', 'DeepSeek V3', 'hosted', 'deepseek-ai/DeepSeek-V3', 'DeepSeek, open weights on Hugging Face', null, null, 131072, 'available'],
            // Downloaded to the local model server (Ollama)
            ['qwen8', 'Qwen3 8B', 'local', 'qwen3:8b', 'ollama pull qwen3:8b', 5.2, 'Q4_K_M', 40960, 'available'],
            ['qwen30', 'Qwen3 30B A3B', 'local', 'qwen3:30b-a3b', 'ollama pull qwen3:30b-a3b', 19.0, 'Q4_K_M', 40960, 'downloading'],
            ['gptoss', 'gpt-oss 20B', 'local', 'gpt-oss:20b', 'ollama pull gpt-oss:20b', 14.0, 'MXFP4', 131072, 'available'],
            ['llama', 'Llama 3.1 8B', 'local', 'llama3.1:8b', 'ollama pull llama3.1:8b', 4.9, 'Q4_K_M', 131072, 'available'],
            ['mistral', 'Mistral 7B', 'local', 'mistral:7b', 'ollama pull mistral:7b', 4.1, 'Q4_0', 32768, 'available'],
            ['billing', 'Billing Assistant (fine-tuned Qwen3 8B)', 'local', 'billing-assistant:8b', '/models/billing-assistant-8b-q4_k_m.gguf, fine-tuned on sample billing questions', 5.2, 'Q4_K_M', 40960, 'available'],
        ] as [$k, $name, $provider, $id, $source, $size, $quant, $ctx, $status]) {
            $m[$k] = AiModel::create(['name' => $name, 'provider' => $provider, 'model_id' => $id, 'source' => $source, 'size_gb' => $size, 'quantization' => $quant,
                'context_window' => $ctx, 'status' => $status, 'checked_at' => now()->subHours(mt_rand(2, 30))]);
        }

        $base = 'You are a customer service agent for a Texas retail electricity provider. Be friendly, brief and accurate. Never guess account details; if you are unsure or the customer is upset, hand the conversation to a person.';
        $agents = [];
        foreach ([
            // Talk to customers: texts and the My Account chat bubble
            ['sms', 'Text Responder', 'sms', 'Replies to customer texts', 'qwen8', 'llama', 0.3, 600, true, null],
            ['chat', 'My Account Chat', 'chat', 'Answers the chat bubble in My Account', 'qwen235', 'qwen8', 0.4, 1500, true, null],
            // Automations: run on a schedule to fix issues in the background
            ['queues', 'Queue Triage', 'queues', 'Moves new orders into the right exception queue', 'billing', 'qwen8', 0.0, 500, true, '*/15 * * * *'],
            ['enrollments', 'Enrollment Fixer', 'enrollments', 'Retries enrollments stuck waiting on ERCOT and fixes address mismatches', 'kimi', 'qwen30', 0.0, 800, true, '0 * * * *'],
            ['payments', 'Payment Matcher', 'payments', 'Matches unapplied payments to the right account', 'billing', 'deepseek', 0.0, 500, true, '30 6 * * *'],
            ['surveys', 'Survey Tagger', 'surveys', 'Tags survey comments by topic and mood', 'mistral', 'gptoss', 0.1, 300, true, '0 2 * * *'],
            ['reports', 'Report Summarizer', 'reports', 'Writes a short summary of finished Walker reports', 'gptoss', 'qwen30', 0.3, 1500, false, '*/30 * * * *'],
        ] as [$k, $name, $activity, $desc, $model, $fallback, $temperature, $max, $active, $schedule]) {
            $agents[$k] = AiAgent::create(['name' => $name, 'activity' => $activity, 'description' => $desc, 'ai_model_id' => $m[$model]->id,
                'fallback_model_id' => $fallback ? $m[$fallback]->id : null, 'temperature' => $temperature, 'max_tokens' => $max, 'active' => $active,
                'schedule' => $schedule, 'system_prompt' => $base."\n\nYour job: ".lcfirst($desc).'.'.($k === 'chat' ? ' If the customer asks for a person, or you can\'t help, answer with HANDOFF on its own line.' : '')]);
        }

        // Conversations: texts and My Account chats, with the issue, transcript, outcome and who took over
        $issues = [
            'High bill' => ['Why is my bill so high this month?', 'Your usage went up about 30% with the heat, and the rate is the same. I can set up a payment arrangement if that helps.'],
            'Payment arrangement' => ['Can I split this bill into two payments?', 'Yes. I can split it over your next two bills; the first half is due on the usual date.'],
            'Moving' => ['I am moving next month, what do I do?', 'I can start service at your new address and stop it at the old one on the same day. What is the new address?'],
            'Outage' => ['My power is out.', 'Outages are handled by your utility. I am texting you their outage number and will check back when power is restored.'],
            'Renewal' => ['My contract is ending, what are my options?', 'You have three renewal plans available. The 12-month plan keeps your rate within half a cent.'],
            'AutoPay' => ['How do I turn on AutoPay?', 'I can turn it on now with the card ending in your saved payment method. You will also earn AutoPay stars each month.'],
            'Rewards' => ['How many stars do I have?', 'You have stars ready to redeem for a bill credit or a gift card. Want me to redeem them?'],
            'Deposit' => ['Why do I need a deposit?', 'The credit check asked for a deposit. You can pay it now, or enroll in AutoPay and paperless billing to lower it.'],
        ];
        $customers = Customer::whereNotNull('phone')->inRandomOrder()->limit(60)->get();
        $csrs = User::whereHas('role', fn ($q) => $q->where('perms', 'like', '%corral%'))->where('active', true)->get();
        foreach (['sms' => 90, 'chat' => 80] as $k => $count) {
            $agent = $agents[$k];
            for ($i = 0; $i < $count; $i++) {
                $topic = array_rand($issues);
                [$q, $a] = $issues[$topic];
                $roll = mt_rand(1, 100);
                $outcome = $roll <= 70 ? 'resolved' : ($roll <= 92 ? 'handed_off' : 'abandoned');
                // A few answered by the fallback model
                $model = mt_rand(1, 12) === 1 && $agent->fallback_model_id ? $agent->fallback_model_id : $agent->ai_model_id;
                $c = $customers->random();
                $lines = ["Bot: Hi {$c->first_name}, thanks for reaching out. How can I help?", "Customer: $q", 'Bot: Let me look at your account.', "Bot: $a"];
                $handed = null;
                if ($outcome === 'handed_off') {
                    $handed = $csrs->random();
                    $lines[] = 'Customer: I would rather talk to a person.';
                    $lines[] = 'Bot: Of course. I am getting a person for you now and passing along what we covered.';
                    $lines[] = 'System: '.$handed->name.' took over.';
                    $lines[] = AiConversation::speakerFor($handed).': Hi '.$c->first_name.', I have your account open. I can help with that.';
                    $lines[] = 'Customer: Thanks, that fixed it.';
                } elseif ($outcome === 'resolved') {
                    $lines[] = 'Customer: That works, thank you.';
                    $lines[] = 'Bot: You are welcome. Have a great day!';
                }
                $start = now()->subDays(mt_rand(0, 29))->setTime(mt_rand(7, 21), mt_rand(0, 59))->min(now()->subMinutes(mt_rand(90, 300)));
                $sec = mt_rand(20, 600);
                AiConversation::create(['ai_agent_id' => $agent->id, 'ai_model_id' => $model, 'customer_id' => $c->id, 'channel' => $k, 'topic' => $topic,
                    'outcome' => $outcome, 'status' => 'closed', 'handed_to_id' => $handed?->id, 'transcript' => implode("\n", $lines), 'duration_sec' => $sec,
                    'input_tokens' => mt_rand(900, 4200), 'output_tokens' => mt_rand(120, 900), 'started_at' => $start, 'last_message_at' => $start->copy()->addSeconds($sec)]);
            }
        }
        // Live right now: two waiting for a person, one a person is answering, two with the bot
        foreach ([['sms', 'waiting', null, 'Payment arrangement', 4], ['chat', 'waiting', null, 'High bill', 2], ['chat', 'agent', $csrs->first(), 'Moving', 6],
            ['sms', 'bot', null, 'Outage', 1], ['chat', 'bot', null, 'Rewards', 3]] as [$k, $status, $person, $topic, $ago]) {
            $c = $customers->random();
            [$q, $a] = $issues[$topic];
            $lines = ["Bot: Hi {$c->first_name}, thanks for reaching out. How can I help?", "Customer: $q", "Bot: $a"];
            if ($status !== 'bot') {
                $lines[] = 'Customer: Can I talk to a person please?';
                $lines[] = 'Bot: Of course. I am getting a person for you now. They will see everything we covered.';
            }
            if ($person) {
                $lines[] = 'System: '.$person->name.' took over.';
                $lines[] = AiConversation::speakerFor($person).': Hi '.$c->first_name.', I can help with the move. What day do you need service at the new address?';
                $lines[] = 'Customer: The 28th.';
            }
            $start = now()->subMinutes($ago + 4);
            AiConversation::create(['ai_agent_id' => $agents[$k]->id, 'ai_model_id' => $agents[$k]->ai_model_id, 'customer_id' => $c->id, 'channel' => $k, 'topic' => $topic,
                'outcome' => $status === 'bot' ? 'resolved' : 'handed_off', 'status' => $status, 'handed_to_id' => $person?->id, 'transcript' => implode("\n", $lines),
                'duration_sec' => 240, 'input_tokens' => mt_rand(400, 1200), 'output_tokens' => mt_rand(80, 300), 'started_at' => $start, 'last_message_at' => now()->subMinutes($ago)]);
        }

        // Automations: one run per schedule tick over the last week (capped), each with what it checked and fixed
        foreach ([
            'queues' => [96, 'Checked {c} new orders: {f} moved to the right queue, {g} left in Unassigned for a person.'],
            'enrollments' => [24, 'Looked at {c} enrollments waiting on ERCOT: retried {f}, {g} need a person (address does not match the ESIID).'],
            'payments' => [1, 'Matched {f} of {c} unapplied payments to accounts; {g} need a person.'],
            'surveys' => [1, 'Tagged {f} of {c} new survey comments; {g} were unclear.'],
        ] as $k => [$perDay, $text]) {
            $agent = $agents[$k];
            $every = intdiv(1440, $perDay);
            for ($i = 0; $i < min(7 * $perDay, 120); $i++) {
                $checked = mt_rand($k === 'payments' ? 4 : 1, $k === 'queues' ? 12 : 40);
                $failed = mt_rand(1, 30) === 1;
                $fixed = $failed ? 0 : mt_rand((int) floor($checked * .6), $checked);
                $flagged = $failed ? 0 : $checked - $fixed;
                AiAutomationRun::create(['ai_agent_id' => $agent->id, 'ai_model_id' => $failed ? null : $agent->ai_model_id, 'status' => $failed ? 'failed' : 'ok',
                    'checked' => $failed ? 0 : $checked, 'fixed' => $fixed, 'flagged' => $flagged,
                    'summary' => $failed ? 'The model did not answer (timed out after 120 seconds). Nothing was changed.' : strtr($text, ['{c}' => $checked, '{f}' => $fixed, '{g}' => $flagged]),
                    'duration_sec' => $failed ? 120 : mt_rand(3, 90), 'input_tokens' => $failed ? 0 : $checked * mt_rand(300, 700), 'output_tokens' => $failed ? 0 : $checked * mt_rand(20, 80),
                    'started_at' => now()->subMinutes(($i + 1) * $every)->startOfMinute()]);
            }
        }
    }
}
