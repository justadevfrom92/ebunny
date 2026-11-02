<?php

namespace App\Models;

use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A PDF header (the band across the top of page 1) or footer (small print on every page). Templates pick one of each, or use the default. */
class PdfPart extends Model
{
    use RecordsHistory;

    public const KINDS = ['header' => 'Header', 'footer' => 'Footer'];

    public const COLORS = ['navy' => 'Navy band, white text', 'cyan' => 'Cyan band, white text', 'white' => 'White, navy text with a cyan line'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function templates(): HasMany
    {
        return $this->hasMany(PdfTemplate::class, $this->kind === 'header' ? 'pdf_header_id' : 'pdf_footer_id');
    }

    public static function defaultFor(string $kind): ?self
    {
        return static::where('kind', $kind)->where('is_default', true)->first();
    }

    /** What the renderer needs from a header. */
    public function headerData(): array
    {
        return ['title' => $this->title, 'subtitle' => $this->subtitle, 'color' => $this->color];
    }
}
