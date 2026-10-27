<?php

namespace App\Http\Middleware;

use App\Support\HeartbeatControl;
use App\Support\SiteTraffic;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps blocked IP addresses out of the website's Laravel pages, and everyone out of blocked areas
 * (Lando → Site → Blocked IPs / Blocked Areas). Static pages ask /site/session instead.
 * Never applies to the admin, so nobody can lock the team out. When the website is taken down in Sheriff → Heartbeat,
 * visitors get a maintenance page (My Account and signed-in staff still get through).
 */
class BlockSiteVisitors
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin', 'admin/*', 'site/session', 'site/ping', 'up')) {
            return $next($request);
        }
        if (HeartbeatControl::isDown('website') && ! $request->is('myaccount', 'myaccount/*') && ! auth('web')->user()?->active) {
            return response()->view('frontend.blocked', ['block' => (object) ['message' => HeartbeatControl::MAINTENANCE]], 503)->header('Retry-After', '600');
        }
        [$path] = SiteTraffic::normalize($request->path());
        if ($block = SiteTraffic::blockFor($request->ip(), $path)) {
            SiteTraffic::hit($block);

            return response()->view('frontend.blocked', ['block' => $block], 403);
        }

        return $next($request);
    }
}
