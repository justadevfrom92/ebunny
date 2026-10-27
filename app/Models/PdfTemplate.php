<?php

namespace App\Models;

use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A letter or notice made into a PDF for an account (Rodeo → PDFs), like an email template is for email. */
class PdfTemplate extends Model
{
    use RecordsHistory;

    public const CATEGORIES = ['Billing', 'Collections', 'Service', 'Welcome', 'Rewards', 'Legal'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['show_address' => 'boolean'];
    }

    public function generations(): HasMany
    {
        return $this->hasMany(PdfGeneration::class);
    }

    public function fileName(?Customer $c): string
    {
        return Str::slug($this->name).($c ? '-'.$c->account : '-sample').'.pdf';
    }
}
