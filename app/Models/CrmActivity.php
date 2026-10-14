<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A call, email, meeting, note, follow-up task or stage change on a lead or partner. */
class CrmActivity extends Model
{
    /** type => [label, verb for the timeline] */
    public const TYPES = [
        'call' => ['Call', 'called'],
        'email' => ['Email', 'emailed'],
        'meeting' => ['Meeting', 'met with'],
        'note' => ['Note', 'noted'],
        'task' => ['Follow-up', 'set a follow-up'],
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'done_at' => 'datetime'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(CrmPartner::class, 'partner_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return $this->type === 'stage' ? 'Stage' : (self::TYPES[$this->type][0] ?? ucfirst($this->type));
    }

    public function isOpenTask(): bool
    {
        return $this->due_on !== null && $this->done_at === null;
    }
}
