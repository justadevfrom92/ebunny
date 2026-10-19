<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One click on a link in an email or campaign (Rodeo → heat maps). */
class LinkClick extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['clicked_at' => 'datetime'];
    }
}
