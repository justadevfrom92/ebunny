<?php

namespace App\Models;

use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function header(): BelongsTo
    {
        return $this->belongsTo(PdfPart::class, 'pdf_header_id');
    }

    public function footerPart(): BelongsTo
    {
        return $this->belongsTo(PdfPart::class, 'pdf_footer_id');
    }

    public function testSends(): HasMany
    {
        return $this->hasMany(PdfTestSend::class);
    }

    /**
     * What the renderer needs: title, body, show_address, the header (the one picked, else the default)
     * and the footer text (this template's own small print, else the footer picked, else the default footer).
     */
    public function document(): array
    {
        $header = $this->header ?? PdfPart::defaultFor('header');
        $footer = trim((string) $this->footer) !== '' ? $this->footer : ($this->footerPart ?? PdfPart::defaultFor('footer'))?->body;

        return ['title' => $this->title, 'body' => $this->body, 'footer' => $footer, 'show_address' => (bool) $this->show_address, 'header' => $header?->headerData()];
    }

    public function fileName(?Customer $c): string
    {
        return Str::slug($this->name).($c ? '-'.$c->account : '-sample').'.pdf';
    }
}
