<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Someone who works Ranger tickets: in-office or 3rd-party, agent or manager. */
class RangerAgent extends Model
{
    public const TEAMS = ['office' => 'In-Office', 'third_party' => '3rd Party'];

    public const LEVELS = ['agent' => 'Agent', 'manager' => 'Manager'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /** Managers can take over a ticket or join in on it. Administrators always can. */
    public static function isManager(?User $u): bool
    {
        if (! $u) {
            return false;
        }

        return $u->hasPerm('sheriff') || self::where('user_id', $u->id)->where('active', true)->where('level', 'manager')->exists();
    }
}
