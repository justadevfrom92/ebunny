<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One heartbeat health check: when, the overall status, and each check's result. */
class Heartbeat extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime', 'results' => 'array'];
    }
}
