<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A report's export columns: model key => column title, in running order. */
class ReportColumn extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['columns' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
