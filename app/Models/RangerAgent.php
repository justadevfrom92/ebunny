<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone who works Ranger tickets: in-office or 3rd-party; agent, manager, or (3rd party) the company
 * admin, who runs their own company's people: adds and changes them, sees their numbers, assigns among them.
 */
class RangerAgent extends Model
{
    public const TEAMS = ['office' => 'In-Office', 'third_party' => '3rd Party'];

    public const LEVELS = ['agent' => 'Agent', 'manager' => 'Manager', 'company_admin' => 'Company Admin'];

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

        return $u->hasPerm('sheriff') || self::where('user_id', $u->id)->where('active', true)->whereIn('level', ['manager', 'company_admin'])->exists();
    }

    /** The 3rd-party company a company admin runs (null for everyone else, including administrators). */
    public static function companyOf(?User $u): ?string
    {
        if (! $u || $u->hasPerm('sheriff') || ! $u->company) {
            return null;
        }

        return self::where('user_id', $u->id)->where('active', true)->where('level', 'company_admin')->exists() ? $u->company : null;
    }

    /** Whether $u can see and change $other's team record (a company admin only their own company's people). */
    public static function canManage(?User $u, User $other): bool
    {
        $company = self::companyOf($u);

        return $company ? $other->company === $company : self::isManager($u);
    }
}
