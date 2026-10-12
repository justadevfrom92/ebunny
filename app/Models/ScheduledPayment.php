<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A My Account payment set to go through at a picked date and time (payments:run-scheduled pays it). */
class ScheduledPayment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['pay_at' => 'datetime', 'amount' => 'decimal:2'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
}
