<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bunkhouse: a one-on-one between a manager and someone on their team. */
class ErmCheckin extends Model
{
    public const MOODS = [1 => 'Struggling', 2 => 'Frustrated', 3 => 'Okay', 4 => 'Good', 5 => 'Great'];

    protected $table = 'erm_checkins';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['held_on' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function moodLabel(): string
    {
        return self::MOODS[$this->mood] ?? '—';
    }
}
