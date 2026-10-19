<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A report's export columns: model key => column title, in running order. */
class ReportColumn extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['columns' => 'array'];
    }
}
