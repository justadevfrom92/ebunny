<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bunkhouse: one person assigned one course. */
class ErmEnrollment extends Model
{
    protected $table = 'erm_enrollments';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['assigned_on' => 'date', 'due_on' => 'date', 'completed_on' => 'date'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(ErmCourse::class, 'course_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function overdue(): bool
    {
        return ! $this->completed_on && $this->due_on->lt(today());
    }

    public function stateLabel(): array
    {
        return match (true) {
            (bool) $this->completed_on => ['Done '.$this->completed_on->format('n/j/Y'), 'ok'],
            $this->overdue() => ['Overdue', 'bad'],
            default => ['Due '.$this->due_on->format('n/j/Y'), 'info'],
        };
    }
}
