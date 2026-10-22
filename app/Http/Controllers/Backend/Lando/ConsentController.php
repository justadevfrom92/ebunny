<?php

namespace App\Http\Controllers\Backend\Lando;

use App\Http\Controllers\Controller;
use App\Models\CookieConsent;
use App\Models\SiteVisit;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Lando → Site → Cookie Consents: every choice made in the website's cookie banner, each with its own page. */
class ConsentController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate(['who' => ['nullable', 'in:customer,employee,anonymous'], 'choice' => ['nullable', 'in:all,necessary,custom'], 'q' => ['nullable', 'string', 'max:60']]);
        $since = today()->subDays(29);
        $totals = CookieConsent::where('created_at', '>=', $since)->selectRaw('choice, count(*) as n')->groupBy('choice')->pluck('n', 'choice');
        $q = trim((string) ($f['q'] ?? ''));

        return view('backend.lando.consents', [
            'f' => $f, 'totals' => $totals, 'all' => $totals->sum(),
            'optIn' => CookieConsent::where('created_at', '>=', $since)->toBase()->selectRaw('sum(analytics) as analytics, sum(marketing) as marketing')->first(),
            'consents' => CookieConsent::with(['customer', 'user'])
                ->when(($f['who'] ?? null) === 'customer', fn ($x) => $x->whereNotNull('customer_id'))
                ->when(($f['who'] ?? null) === 'employee', fn ($x) => $x->whereNotNull('user_id'))
                ->when(($f['who'] ?? null) === 'anonymous', fn ($x) => $x->whereNull('customer_id')->whereNull('user_id'))
                ->when($f['choice'] ?? null, fn ($x, $c) => $x->where('choice', $c))
                ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('visitor', 'like', $q.'%')->orWhere('ip', 'like', $q.'%')
                    ->orWhereHas('customer', fn ($c) => $c->where('account', 'like', $q.'%')->orWhere('name', 'like', '%'.$q.'%'))))
                ->latest('id')->paginate(10)->withQueryString(),
        ]);
    }

    /** One choice: who, what they allowed, the cookies their browser actually had, their other choices and visits. */
    public function show(CookieConsent $consent): View
    {
        $consent->load(['customer', 'user']);
        $jar = $consent->cookie_jar ?? [];

        return view('backend.lando.consent', [
            'k' => $consent,
            'jar' => $jar,
            'unknown' => array_diff_key($jar, CookieConsent::COOKIES),
            'others' => CookieConsent::where('id', '!=', $consent->id)->where(fn ($q) => $q->where('visitor', $consent->visitor)
                ->when($consent->customer_id, fn ($w) => $w->orWhere('customer_id', $consent->customer_id)))->latest('id')->limit(50)->get(),
            'visits' => SiteVisit::where('visitor', $consent->visitor)->latest('created_at')->limit(50)->get(),
        ]);
    }

    /** One of the site's cookies: what it is, how many choices allow it, and every browser it was seen in. */
    public function cookie(string $name): View
    {
        abort_unless(isset(CookieConsent::COOKIES[$name]), 404);
        [$cat] = CookieConsent::COOKIES[$name];
        $since = today()->subDays(29);
        $recent = CookieConsent::where('created_at', '>=', $since);
        $allows = fn ($q) => $cat === 'necessary' ? $q : $q->where($cat, true);
        $seen = CookieConsent::with(['customer', 'user'])->whereRaw('json_extract(cookie_jar, ?) is not null', ['$."'.$name.'"'])->latest('cookies_seen_at')->limit(200)->get();

        return view('backend.lando.cookie', [
            'name' => $name, 'cookie' => CookieConsent::COOKIES[$name],
            'choices' => (clone $recent)->count(), 'allowing' => $allows(clone $recent)->count(),
            'seen' => $seen, 'notAllowed' => $seen->filter(fn ($k) => ! in_array($name, $k->cookies(), true))->values(),
        ]);
    }
}
