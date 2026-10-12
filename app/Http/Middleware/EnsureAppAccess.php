<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a signed-in user into an admin app only when their role includes it.
 * Usage: ->middleware('app:corral')
 */
class EnsureAppAccess
{
    public function handle(Request $request, Closure $next, string $app): Response
    {
        $user = $request->user();

        if (! $user->active) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('admin.login')->withErrors(['email' => 'This account is disabled.']);
        }

        if (! $user->hasPerm($app)) {
            return redirect()->route('admin.launcher', ['denied' => $app]);
        }

        return $next($request);
    }
}
