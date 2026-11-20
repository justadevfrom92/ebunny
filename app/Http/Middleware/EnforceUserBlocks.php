<?php

namespace App\Http\Middleware;

use App\Models\UserBlock;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Signs out a device the employee blocked (My Profile → Signed In On), even one kept signed in by "remember me". */
class EnforceUserBlocks
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();
        if ($user && ! $request->session()->has('impersonator_id') && UserBlock::blocks($user, $request)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->withErrors(['email' => 'This device was blocked from signing in to that account. Ask an administrator if you think this is wrong.']);
        }

        return $next($request);
    }
}
