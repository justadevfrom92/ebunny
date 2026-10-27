<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One run of a scheduled job, recorded by App\Console\Commands\TrackedCommand. */
class JobRun extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    /** The job's name from config/admin.php (Sheriff → Crons). */
    public function jobName(): string
    {
        return config('admin.jobs.'.$this->command)[0] ?? $this->command;
    }

    public function tone(): string
    {
        return ['OK' => 'ok', 'Warning' => 'warn', 'Failed' => 'bad', 'Skipped' => 'warn', 'Running' => 'info'][$this->status] ?? '';
    }
}
