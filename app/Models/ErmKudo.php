<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bunkhouse: a thank-you from one person to another, tied to a company value. */
class ErmKudo extends Model
{
    public const VALUES = ['Customer First', 'Teamwork', 'Own It', 'Keep It Simple', 'Lone Star Spirit'];

    protected $table = 'erm_kudos';

    protected $guarded = ['id'];

    public function from(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_id');
    }
}
