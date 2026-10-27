<?php

namespace App\Models;

use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Model;

/** Build Your Own Plan add-on. `key` matches the option's data-product on the website. */
class ByopProduct extends Model
{
    use RecordsHistory;

    /** The website page's tabs, in order; each product shows on its step's tab. */
    public const STEPS = ['Get Started', 'Lower Your Bill', 'Using Less', 'Save More', "Protectin'"];

    public const TYPES = ['Discount', 'Free', 'Monthly', 'Premium', 'Included'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rate_adj' => 'float', 'monthly' => 'float', 'active' => 'boolean'];
    }
}
