<?php

namespace App\Http\Controllers\Frontend\MyAccount;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\Deputy\LiveConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The My Account chat bubble (bottom right). The Deputy chat bot answers; the customer can ask
 * for a person, and once an agent takes over in Deputy their replies show up here.
 */
class ChatController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->state(LiveConversation::chatFor($request->user('customer')));
    }

    public function send(Request $request, LiveConversation $live): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:1000']]);
        $conv = LiveConversation::chatFor($request->user('customer'), true);
        $live->customerSays($conv, $data['message']);

        return $this->state($conv->fresh());
    }

    /** "Talk to a person": the bot hands the chat to Deputy → Conversations → Needs a Person. */
    public function person(Request $request, LiveConversation $live): JsonResponse
    {
        $conv = LiveConversation::chatFor($request->user('customer'), true);
        if ($conv->status === 'bot') {
            $live->handOff($conv, 'I am getting a person for you now. They will see everything we covered.');
        }

        return $this->state($conv->fresh());
    }

    /** What the bubble shows: who is answering and the messages (system notes reworded for the customer). */
    private function state(?AiConversation $conv): JsonResponse
    {
        if (! $conv) {
            return response()->json(['status' => 'new', 'messages' => []]);
        }
        $messages = collect($conv->lines())->map(function ($l) {
            [$who, $text] = $l;
            if ($who === 'System') {
                return preg_match('/^(.+) took over\.$/', $text, $m) ? ['from' => 'note', 'text' => explode(' ', $m[1])[0].' from our team joined the chat.'] : null;
            }

            return $who === 'Customer' ? ['from' => 'me', 'text' => $text]
                : ['from' => str_ends_with($who, '(agent)') ? 'person' : 'bot', 'name' => str_ends_with($who, '(agent)') ? explode(' ', $who)[0] : 'Assistant', 'text' => $text];
        })->filter()->values();

        return response()->json(['status' => $conv->status, 'messages' => $messages]);
    }
}
