<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Bunkhouse: an HR case about one person (a concern, coaching, an accommodation, a question). Only Bunkhouse users see these. */
class ErmCase extends Model
{
    public const TYPES = ['Concern', 'Coaching', 'Accommodation', 'Policy Question', 'Conflict', 'Performance Plan', 'Pay or Benefits'];

    public const PRIORITIES = ['Low', 'Medium', 'High'];

    public const STATUSES = ['open' => ['Open', 'info'], 'in_progress' => ['In Progress', 'warn'], 'closed' => ['Closed', 'ok']];

    protected $table = 'erm_cases';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ErmCaseUpdate::class, 'case_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusTone(): string
    {
        return self::STATUSES[$this->status][1] ?? '';
    }
}
