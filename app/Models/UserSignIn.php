<?php

namespace App\Models;

use App\Http\Controllers\Backend\AccountController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/** One admin sign-in: who, where, which browser, and how it ended (null while still signed in or until it expires). */
class UserSignIn extends Model
{
    public const HOW = ['password' => 'Email and password', 'test' => 'Test sign-in', 'log_in_as' => 'Log in as', 'back_from_log_in_as' => 'Back from Log in as'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['signed_in_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function impersonator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonator_id');
    }

    public function browser(): string
    {
        return AccountController::browser((string) $this->user_agent);
    }

    /** Record a sign-in for the request's (already regenerated) session. */
    public static function start(Request $request, User $user, string $how = 'password', ?int $impersonatorId = null): self
    {
        self::end($request, 'Signed in again');   // the session it replaces, if any
        $request->session()->put('signed_in_at', now()->timestamp);

        return tap(self::create(['user_id' => $user->id, 'impersonator_id' => $impersonatorId, 'session_key' => hash('sha256', $request->session()->getId()),
            'ip' => $request->ip(), 'user_agent' => (string) $request->userAgent(), 'how' => $how, 'signed_in_at' => now()]), fn ($s) => $request->session()->put('sign_in_id', $s->id));
    }

    /** Close the sign-in of a session (this request's, or the one with that session id). */
    public static function end(Request|string $session, string $reason): void
    {
        $done = ['ended_at' => now(), 'ended_reason' => $reason, 'session_key' => null];
        if ($session instanceof Request && $session->hasSession() && ($signInId = $session->session()->pull('sign_in_id'))) {
            self::whereKey($signInId)->whereNull('ended_at')->update($done);
        }
        $id = $session instanceof Request ? ($session->hasSession() ? $session->session()->getId() : null) : $session;
        if ($id) {
            self::where('session_key', hash('sha256', $id))->whereNull('ended_at')->update($done);
        }
    }
}
