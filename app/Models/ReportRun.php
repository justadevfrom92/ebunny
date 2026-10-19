<?php

namespace App\Models;

use App\Support\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** One run of a report: from Corral's report screens or Walker. Shown on Walker's home page by status. */
class ReportRun extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['params' => 'array', 'preview' => 'array', 'created_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime',
            'seen_at' => 'datetime', 'summary' => 'boolean'];
    }

    /** Finished (done or failed) runs this person started and hasn't seen yet: the number by their name, top right. */
    public function scopeUnseenFor(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId)->whereNull('seen_at')->whereIn('status', ['done', 'failed']);
    }

    /** "Notes report (CSV)" */
    public function label(): string
    {
        return ($this->title ?? Str::headline($this->report)).($this->summary ? ' summary' : '');
    }

    /** "12.4 KB" */
    public function sizeLabel(): ?string
    {
        if ($this->file_size === null) {
            return null;
        }

        return $this->file_size >= 1048576 ? number_format($this->file_size / 1048576, 1).' MB' : number_format(max(0.1, $this->file_size / 1024), 1).' KB';
    }

    public function formatLabel(): string
    {
        return Export::FORMATS[$this->format][0] ?? strtoupper((string) $this->format);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** queued | running | done | failed | stalled (still running past walker.timeout_minutes: "Did not finish") */
    public function state(): string
    {
        if (in_array($this->status, ['queued', 'running'], true) && ($this->started_at ?? $this->created_at)->lt(now()->subMinutes(config('walker.timeout_minutes')))) {
            return 'stalled';
        }

        return $this->status;
    }

    public static function stateLabel(string $state): array
    {
        return ['queued' => ['Queued', 'info'], 'running' => ['Running', 'info'], 'done' => ['Completed', 'ok'], 'failed' => ['Error', 'bad'], 'stalled' => ['Did not finish', 'warn']][$state] ?? [$state, ''];
    }

    public function scopeInState(Builder $q, string $state): Builder
    {
        $cut = now()->subMinutes(config('walker.timeout_minutes'));

        return match ($state) {
            'running' => $q->whereIn('status', ['queued', 'running'])->where(fn ($w) => $w->where('started_at', '>=', $cut)->orWhere(fn ($x) => $x->whereNull('started_at')->where('created_at', '>=', $cut))),
            'done' => $q->where('status', 'done'),
            'problem' => $q->where(fn ($w) => $w->where('status', 'failed')->orWhere(fn ($x) => $x->whereIn('status', ['queued', 'running'])
                ->where(fn ($y) => $y->where('started_at', '<', $cut)->orWhere(fn ($z) => $z->whereNull('started_at')->where('created_at', '<', $cut))))),
            default => $q,
        };
    }
}
