<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bunkhouse: a time-off request. */
class ErmTimeOff extends Model
{
    public const TYPES = ['PTO', 'Sick', 'Jury Duty', 'Bereavement', 'Volunteer', 'Unpaid'];

    protected $table = 'erm_time_off';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'decided_at' => 'datetime', 'hours' => 'float'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /** Approved and overlapping the given days. */
    public function scopeOutBetween(Builder $q, $from, $to): Builder
    {
        return $q->where('status', 'approved')->whereDate('starts_on', '<=', $to)->whereDate('ends_on', '>=', $from);
    }

    public function dates(): string
    {
        return $this->starts_on->equalTo($this->ends_on) ? $this->starts_on->format('D n/j/Y') : $this->starts_on->format('D n/j').' – '.$this->ends_on->format('D n/j/Y');
    }

    public function statusTone(): string
    {
        return ['approved' => 'ok', 'denied' => 'bad'][$this->status] ?? 'info';
    }
}
