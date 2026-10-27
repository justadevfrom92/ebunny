<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A call to an outside API (Experian, Stripe, Utilibill…), with what was sent and what came back (secrets blanked). */
class ApiLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'request' => 'array', 'response' => 'array'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function ok(): bool
    {
        return str_starts_with((string) $this->status, '2');
    }
}
