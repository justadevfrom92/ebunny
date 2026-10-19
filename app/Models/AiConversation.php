<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One conversation with a customer by text or in the My Account chat (or an agent test), with its transcript.
 * Live ones say who is answering now: the bot, nobody yet (waiting for a person), or a person.
 */
class AiConversation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime', 'started_at' => 'datetime', 'last_message_at' => 'datetime'];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function handedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handed_to_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isLive(): bool
    {
        return $this->status !== 'closed';
    }

    /** Adds "Speaker: text" to the transcript (one line; newlines become spaces). */
    public function say(string $who, string $text): void
    {
        $line = $who.': '.trim(preg_replace('/\s+/', ' ', $text));
        $this->transcript = ltrim($this->transcript."\n".$line, "\n");
        $this->last_message_at = now();
        $this->duration_sec = max(0, (int) $this->started_at->diffInSeconds(now()));
        $this->save();
    }

    /** People who answered show as "Name (agent)"; the AI is "Bot". */
    public static function speakerFor(User $user): string
    {
        return $user->name.' (agent)';
    }

    /** [[speaker, text], …] from "Speaker: text" lines. */
    public function lines(): array
    {
        return collect(preg_split("/\r?\n/", $this->transcript, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($l) => str_contains($l, ':') ? array_map('trim', explode(':', $l, 2)) : ['', trim($l)])->all();
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
