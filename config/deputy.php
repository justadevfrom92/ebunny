<?php

/*
| Deputy — AI agents on open-weight models. Two ways to run a model, both set up in
| Sheriff → APIs → Configure:
|  - local:  downloaded to your own server and served by Ollama (LOCAL_MODELS_URL, its /api/chat)
|  - hosted: any OpenAI-compatible endpoint (OPEN_MODELS_URL + OPEN_MODELS_API_KEY), e.g. vLLM,
|            llama.cpp server, LM Studio, OpenRouter, Together or Moonshot (Kimi)
|
| Agents either talk to customers (texts and the My Account chat; those are the Conversations)
| or run on a schedule to fix issues in the background (Automations). No phone calls or email.
*/
return [
    'providers' => [
        'local' => 'Local server (Ollama)',
        'hosted' => 'Hosted (OpenAI-compatible)',
    ],

    // What each agent does in the business
    'activities' => [
        'sms' => 'Replies to texts',
        'chat' => 'My Account chat',
        'queues' => 'Triages exception queues',
        'enrollments' => 'Fixes stuck enrollments',
        'payments' => 'Matches unapplied payments',
        'surveys' => 'Tags survey comments',
        'reports' => 'Summarizes reports',
    ],
    // The activities that talk to customers; every other activity is an automation that runs on a schedule
    'conversation_activities' => ['sms', 'chat'],

    'channels' => ['sms' => 'Text', 'chat' => 'My Account Chat', 'test' => 'Test'],
    'outcomes' => ['resolved' => 'Resolved', 'handed_off' => 'Handed to a person', 'abandoned' => 'Customer left', 'failed' => 'Model error'],
    // Who is answering a conversation right now
    'statuses' => ['bot' => 'Bot answering', 'waiting' => 'Waiting for a person', 'agent' => 'Person answering', 'closed' => 'Closed'],

    // A chat with no new message for this long is closed, and the customer's next message starts a new one
    'chat_idle_minutes' => 30,
];
