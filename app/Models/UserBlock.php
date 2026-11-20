<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/** A device (IP address + browser) blocked from signing in to someone's admin account. */
class UserBlock extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reported_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Is this request coming from a device the user blocked? */
    public static function blocks(User $user, Request $request): bool
    {
        return self::where('user_id', $user->id)->where('ip', $request->ip())->where('user_agent', (string) $request->userAgent())->exists();
    }
}
