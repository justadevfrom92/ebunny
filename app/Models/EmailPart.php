<?php

namespace App\Models;

use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An email header or footer. Templates pick one of each; when they don't, the default is used. */
class EmailPart extends Model
{
    use RecordsHistory;

    public const KINDS = ['header' => 'Header', 'footer' => 'Footer'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function templates(): HasMany
    {
        return $this->hasMany(EmailTemplate::class, $this->kind === 'header' ? 'email_header_id' : 'email_footer_id');
    }
}
