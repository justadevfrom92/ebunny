<?php

namespace App\Models;

use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An AI agent: what it does (activity), the model it runs on, its instructions, and for automations its schedule. */
class AiAgent extends Model
{
    use RecordsHistory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }

    public function fallback(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'fallback_model_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AiAutomationRun::class);
    }

    /** True for agents that talk to customers (texts, My Account chat); false for scheduled automations. */
    public function talksToCustomers(): bool
    {
        return in_array($this->activity, config('deputy.conversation_activities'), true);
    }

    public function scopeAutomations($q)
    {
        return $q->whereNotIn('activity', config('deputy.conversation_activities'));
    }

    /** The agent that answers a channel (sms, chat), if one is active. */
    public static function for(string $channel): ?self
    {
        return self::where('activity', $channel)->where('active', true)->with(['model', 'fallback'])->orderBy('id')->first();
    }

    public function activityLabel(): string
    {
        return config('deputy.activities.'.$this->activity, $this->activity);
    }
}
