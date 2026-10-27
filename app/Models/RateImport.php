<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A rate file uploaded in Astro: read in the background, then confirmed (made live) or discarded. */
class RateImport extends Model
{
    public const LABELS = ['queued' => ['Waiting to Run', 'info'], 'processing' => ['Reading the File', 'info'], 'ready' => ['Ready to Confirm', 'warn'],
        'failed' => ['Failed', 'bad'], 'applied' => ['Live', 'ok'], 'discarded' => ['Discarded', '']];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'problems' => 'array', 'effective_on' => 'date', 'applied_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function applier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function running(): bool
    {
        return in_array($this->status, ['queued', 'processing'], true);
    }
}
