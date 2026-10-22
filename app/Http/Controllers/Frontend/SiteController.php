<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\CookieConsent;
use App\Models\SiteVisit;
use App\Services\Cart;
use App\Services\Catalog;
use App\Support\SiteTraffic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/** The few things the static website needs from Laravel. */
class SiteController extends Controller
{
    public function home()
    {
        return response()->file(public_path('frontend/index.html'));
    }

    /** /plans.html and the other built-in pages, from public/frontend/. */
    public function file(string $page)
    {
        return response()->file(public_path('frontend/'.$page.'.html'));
    }

    /** /shared/config/brand.js — from config/brand.php */
    public function brand(): Response
    {
        return $this->script('ET.brand = '.json_encode(config('brand'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).';');
    }

    /** /shared/config/catalog.js — plans, rates, fees and pricing modifiers from the database */
    public function catalog(Catalog $catalog): Response
    {
        return $this->script('ET.defaults = '.json_encode($catalog->siteData(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).';');
    }

    /** CSRF token for the website's forms, and who (if anyone) is signed in to the admin. */
    public function session(Request $request): JsonResponse
    {
        $user = $request->user();
        // Each page asks once as it opens: that's the page view (Lando → Site → Visitors)
        [$visit, $block] = $request->filled('path') ? SiteTraffic::record($request, (string) $request->query('path'), $request->query('ref')) : [null, null];

        $response = response()->json([
            'csrf' => csrf_token(),
            'user' => $user?->active ? ['name' => $user->name] : null,
            // A customer signed in to My Account gets Sign Out in the website header
            'customer' => ($c = auth('customer')->user()) ? ['name' => $c->first_name] : null,
            'cart' => app(Cart::class)->count(),
            'visit' => $visit && ! $block ? $visit->id : null,
            // The static pages hide themselves when the visitor is blocked
            'blocked' => $block ? ['message' => $block->message ?: 'This page isn\'t available.'] : null,
            // The cookie banner shows until this browser (or this signed-in customer) has chosen
            'consent' => ($k = CookieConsent::current($request, $visit?->visitor ?? self::visitor($request))) ? ['analytics' => $k->analytics, 'marketing' => $k->marketing, 'choice' => $k->choice] : null,
        ])->header('Cache-Control', 'no-store');

        return $visit ? $response->withCookie(cookie(SiteTraffic::COOKIE, $visit->visitor, 60 * 24 * 365)) : $response;
    }

    /** This browser's visitor id (the et_vid cookie), when it has one. */
    private static function visitor(Request $request): ?string
    {
        $v = $request->cookie(SiteTraffic::COOKIE);

        return is_string($v) && preg_match('/^[a-zA-Z0-9]{20,40}$/', $v) ? $v : null;
    }

    /**
     * The cookie banner's answer: Accept all, Necessary only, or chosen categories. Stored for the
     * browser and, when someone is signed in, for that customer or employee too.
     */
    public function consent(Request $request): JsonResponse
    {
        $data = $request->validate(['choice' => ['required', 'in:all,necessary,custom'], 'analytics' => ['boolean'], 'marketing' => ['boolean'], 'page' => ['nullable', 'string', 'max:250']]);
        $visitor = self::visitor($request) ?? Str::random(32);
        [$analytics, $marketing] = match ($data['choice']) {
            'all' => [true, true],
            'necessary' => [false, false],
            default => [(bool) ($data['analytics'] ?? false), (bool) ($data['marketing'] ?? false)],
        };
        CookieConsent::create(['visitor' => $visitor, 'customer_id' => auth('customer')->id(), 'user_id' => auth('web')->id(), 'choice' => $data['choice'],
            'analytics' => $analytics, 'marketing' => $marketing, 'policy_version' => CookieConsent::POLICY_VERSION, 'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 245, ''), 'page' => $data['page'] ?? null, 'created_at' => now(),
            'cookie_jar' => CookieConsent::jarFrom($request), 'cookies_seen_at' => now()]);

        return response()->json(['consent' => ['analytics' => $analytics, 'marketing' => $marketing, 'choice' => $data['choice']]])
            ->withCookie(cookie(SiteTraffic::COOKIE, $visitor, 60 * 24 * 365));
    }

    /** The open page's heartbeat, so Lando can show who is on the site now. */
    public function ping(Request $request): JsonResponse
    {
        $visitor = $request->cookie(SiteTraffic::COOKIE);
        if ($visitor && $id = $request->integer('v')) {
            SiteVisit::whereKey($id)->where('visitor', $visitor)->where('created_at', '>=', now()->subHours(12))->update(['seen_at' => now()]);
        }
        // Keep the cookie cache on this browser's latest choice current (at most every 10 minutes)
        if ($visitor) {
            CookieConsent::where('visitor', $visitor)->latest('id')->limit(1)->where(fn ($q) => $q->whereNull('cookies_seen_at')->orWhere('cookies_seen_at', '<', now()->subMinutes(10)))
                ->get()->each(fn ($c) => $c->update(['cookie_jar' => CookieConsent::jarFrom($request), 'cookies_seen_at' => now()]));
        }

        return response()->json(['ok' => true])->header('Cache-Control', 'no-store');
    }

    /** Contact Us form → Corral → Web Messages */
    public function contact(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first' => ['required', 'string', 'max:100'],
            'last' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:200'],
            'phone' => ['nullable', 'string', 'max:40'],
            'topic' => ['required', 'string', 'max:40'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        ContactMessage::create([
            'first_name' => $data['first'], 'last_name' => $data['last'], 'email' => $data['email'],
            'phone' => $data['phone'] ?? null, 'topic' => $data['topic'], 'message' => $data['message'],
            'ip' => $request->ip(),
        ]);

        return response()->json(['ok' => true], 201);
    }

    private function script(string $js): Response
    {
        return response("window.ET = window.ET || {};\n".$js."\n", 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
