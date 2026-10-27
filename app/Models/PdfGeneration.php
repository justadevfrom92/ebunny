<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One PDF made from a template for an account, with a copy of the wording it was made from. */
class PdfGeneration extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['show_address' => 'boolean', 'created_at' => 'datetime'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PdfTemplate::class, 'pdf_template_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
