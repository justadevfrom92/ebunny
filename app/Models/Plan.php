<?php

namespace App\Models;

use App\Models\Concerns\RecordsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Plan extends Model
{
    use RecordsHistory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tags' => 'array', 'perks' => 'array', 'active' => 'boolean', 'mrc' => 'float', 'term' => 'integer', 'green' => 'integer'];
    }

    public function rates(): HasMany
    {
        return $this->hasMany(Rate::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(PlanGroup::class, 'plan_group_plan')->withPivot('position');
    }

    /** First dollar amount in the ETF text ("$300/$50x" → 300), or 0. */
    public function etfAmount(): int
    {
        return preg_match('/\d+/', (string) $this->etf, $m) ? (int) $m[0] : 0;
    }

    /**
     * The plan page address (/plans/{this}): the slug. When several plans (versions of the
     * same plan) share a slug, the first keeps it and the others add their code (slug-etm2).
     */
    public function pageSlug(): string
    {
        $first = once(fn () => self::selectRaw('slug, min(id) as id')->groupBy('slug')->pluck('id', 'slug')->all());

        return ($first[$this->slug] ?? $this->id) === $this->id ? $this->slug : $this->slug.'-'.Str::slug($this->internal);
    }

    public static function forPage(string $page): ?self
    {
        return self::where('active', true)->get()->first(fn ($p) => $p->pageSlug() === $page);
    }
}
