<?php

namespace App\Models;

use App\Reports\Report;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A Walker report with its settings, bookmarked to open or rerun in one click (yours, or shared with everyone). */
class SavedReport extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['params' => 'array', 'summary' => 'boolean', 'shared' => 'boolean', 'last_run_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ReportRun::class);
    }

    /** Yours, plus the ones others shared. */
    public function scopeVisibleTo(Builder $q, User $u): void
    {
        $q->where(fn ($w) => $w->where('user_id', $u->id)->orWhere('shared', true));
    }

    public function definition(): ?array
    {
        return config('walker.reports.'.$this->report) ? Report::definition($this->report) : null;
    }

    /** "Start: 2026-01-01 · Statuses: Good - On Flow" */
    public function settingsLine(): string
    {
        return collect($this->params ?? [])->filter(fn ($v) => $v !== null && $v !== '' && $v !== [])
            ->map(fn ($v, $k) => str($k)->headline().': '.(is_array($v) ? implode(', ', $v) : $v))->implode(' · ') ?: 'Default settings';
    }
}
