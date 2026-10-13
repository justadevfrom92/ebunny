<?php

namespace App\Models;

use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentBlock extends Model
{
    use RecordsHistory;

    protected $guarded = ['id'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlockCategory::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(PageComponent::class);
    }

    /** {{name}} or {{name|default text}} in the block's HTML. */
    public const VAR = '/\{\{\s*([a-z][a-z0-9_]*)\s*(?:\|([^}]*))?\}\}/i';

    /**
     * The block's variables, in order: [name => default text]. A block with variables is a
     * module: each page that uses it passes its own text for each one.
     */
    public function variables(): array
    {
        preg_match_all(self::VAR, (string) $this->html, $m, PREG_SET_ORDER);
        $vars = [];
        foreach ($m as $x) {
            $vars[$x[1]] ??= trim($x[2] ?? '');
        }

        return $vars;
    }

    /** The block's HTML with its variables filled in (as text): passed values, else each default. */
    public function withVars(array $values = []): string
    {
        return preg_replace_callback(self::VAR, fn ($x) => e(array_key_exists($x[1], $values) && $values[$x[1]] !== '' && $values[$x[1]] !== null ? $values[$x[1]] : trim($x[2] ?? '')), (string) $this->html);
    }

    /** The code to paste into page content, with a slot for each variable. */
    public function shortcode(): string
    {
        return '[[block|'.$this->slug.collect($this->variables())->map(fn ($d, $k) => '|'.$k.'='.($d !== '' ? $d : '…'))->implode('').']]';
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
