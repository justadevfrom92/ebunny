<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An order placed from the Shop cart in My Account → Checkout. */
class ShopOrder extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['items' => 'array', 'ship_to' => 'array', 'subtotal' => 'decimal:2', 'tax' => 'decimal:2', 'shipping' => 'decimal:2', 'total' => 'decimal:2', 'monthly' => 'decimal:2'];
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** The employee who placed it by phone (none when the customer checked out in My Account). */
    public function placedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by');
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
}
