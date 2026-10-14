<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bunkhouse: a performance review (annual, mid-year, 90-day, or a contractor check-in). */
class ErmReview extends Model
{
    public const KINDS = ['Annual', 'Mid-year', '90-day', 'Contractor check-in'];

    /** status => [label, tone] */
    public const STATUSES = ['scheduled' => ['Scheduled', 'info'], 'self' => ['Self Review', 'warn'], 'manager' => ['Manager Review', 'warn'], 'complete' => ['Complete', 'ok']];

    public const RATINGS = [1 => 'Below expectations', 2 => 'Partly meets', 3 => 'Meets expectations', 4 => 'Exceeds', 5 => 'Outstanding'];

    protected $table = 'erm_reviews';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'completed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? ucfirst($this->status);
    }

    public function statusTone(): string
    {
        return $this->overdue() ? 'bad' : (self::STATUSES[$this->status][1] ?? '');
    }

    public function overdue(): bool
    {
        return $this->status !== 'complete' && $this->due_on->lt(today());
    }
}
