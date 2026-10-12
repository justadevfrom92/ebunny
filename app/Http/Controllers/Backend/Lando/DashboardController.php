<?php

namespace App\Http\Controllers\Backend\Lando;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\HistoryItem;
use App\Models\Page;
use App\Models\SiteBlock;
use App\Models\SiteVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Lando → Site: the dashboard (Lando's home), site health checks and the visitors list. */
class DashboardController extends Controller
{
    public function dashboard(): View
    {
        $today = today();
        $views = SiteVisit::where('blocked', false);

        return view('backend.lando.site.dashboard', [
            'checks' => $checks = self::checks(),
            'problems' => collect($checks)->whereIn('state', ['bad', 'warn'])->count(),
            'online' => SiteVisit::online()->where('blocked', false)->distinct()->count('visitor'),
            'onlineCustomers' => SiteVisit::online()->whereNotNull('customer_id')->distinct()->count('customer_id'),
            'visitorsToday' => (clone $views)->where('created_at', '>=', $today)->distinct()->count('visitor'),
            'viewsToday' => (clone $views)->where('created_at', '>=', $today)->count(),
            'visitors30' => (clone $views)->where('created_at', '>=', $today->copy()->subDays(29))->distinct()->count('visitor'),
            'accounts' => Customer::whereNotNull('password')->count(),
            'customers' => Customer::count(),
            'signedInToday' => (clone $views)->where('created_at', '>=', $today)->whereNotNull('customer_id')->distinct()->count('customer_id'),
            'blockedIps' => SiteBlock::where('type', 'ip')->inForce()->count(),
            'blockedAreas' => SiteBlock::where('type', 'area')->inForce()->count(),
            'blockedToday' => SiteVisit::where('blocked', true)->where('created_at', '>=', $today)->count(),
            'hours' => self::hours(),
            'topPages' => (clone $views)->where('created_at', '>=', $today)->selectRaw('path, page_id, count(*) as n, count(distinct visitor) as people')
                ->groupBy('path', 'page_id')->orderByDesc('n')->limit(8)->get(),
            'onPages' => SiteVisit::online()->where('blocked', false)->selectRaw('path, page_id, count(distinct visitor) as n')->groupBy('path', 'page_id')->orderByDesc('n')->limit(8)->get(),
            'recentBlocks' => SiteVisit::where('blocked', true)->latest()->limit(6)->get(),
            'changes' => HistoryItem::with('user')->whereIn('model', ['Page_model', 'ContentBlock_model', 'SiteBlock_model', 'Site_model'])->latest('id')->limit(6)->get(),
        ]);
    }

    public function health(): View
    {
        return view('backend.lando.site.health', ['checks' => self::checks(), 'about' => [
            'PHP' => PHP_VERSION, 'Laravel' => app()->version(), 'Environment' => app()->environment(),
            'Database' => config('database.default'), 'Cache' => config('cache.default'), 'Sessions' => config('session.driver'),
            'Mail' => config('mail.default'), 'Queue' => config('queue.default'), 'Checked' => now()->format('n/j/Y g:i:s A'),
        ]]);
    }

    public function visitors(Request $request): View
    {
        $f = $request->only(['ip', 'path', 'visitor', 'start', 'end', 'who', 'blocked']);
        $q = SiteVisit::with(['customer', 'page'])
            ->when($f['ip'] ?? null, fn ($q, $ip) => $q->where('ip', 'like', str_replace('*', '%', trim($ip)).(str_contains($ip, '*') ? '' : '%')))
            ->when($f['path'] ?? null, fn ($q, $p) => $q->where('path', 'like', '%'.trim($p, ' /').'%'))
            ->when($f['visitor'] ?? null, fn ($q, $v) => $q->where('visitor', $v))
            ->when($f['start'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($f['end'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when(($f['who'] ?? null) === 'online', fn ($q) => $q->online())
            ->when(($f['who'] ?? null) === 'customers', fn ($q) => $q->whereNotNull('customer_id'))
            ->when(($f['blocked'] ?? null) !== null && ($f['blocked'] ?? '') !== '', fn ($q) => $q->where('blocked', (bool) $f['blocked']));

        return view('backend.lando.site.visitors', [
            'f' => $f,
            'visits' => $q->latest()->latest('id')->paginate(50)->withQueryString(),
            'blocked' => SiteBlock::where('type', 'ip')->inForce()->get(),
            'online' => SiteVisit::online()->where('blocked', false)->distinct()->count('visitor'),
        ]);
    }

    /** Page views per hour today (and the same hour yesterday, for comparison). */
    private static function hours(): array
    {
        $count = fn ($from) => SiteVisit::where('blocked', false)->whereBetween('created_at', [$from, $from->copy()->endOfDay()])->get(['created_at'])
            ->countBy(fn ($v) => (int) $v->created_at->format('G'));
        $today = $count(today());
        $yesterday = $count(today()->subDay());

        return collect(range(0, 23))->map(fn ($h) => ['hour' => $h, 'today' => $today[$h] ?? 0, 'yesterday' => $yesterday[$h] ?? 0, 'future' => $h > now()->hour])->all();
    }

    /** Website health: each check is [name, state ok|warn|bad, what we found, what to do]. */
    public static function checks(): array
    {
        $checks = [];
        $add = function (string $group, string $name, string $state, string $found, ?string $fix = null) use (&$checks) {
            $checks[] = compact('group', 'name', 'state', 'found', 'fix');
        };

        // Server
        try {
            $t = microtime(true);
            DB::select('select 1');
            $ms = round((microtime(true) - $t) * 1000, 1);
            $add('Server', 'Database', $ms > 200 ? 'warn' : 'ok', 'Answering in '.$ms.' ms', $ms > 200 ? 'The database is slow to answer.' : null);
        } catch (\Throwable $e) {
            $add('Server', 'Database', 'bad', 'Not answering', 'Check the DB_* settings in .env.');
        }
        try {
            Cache::put('health-check', $v = Str::random(8), 10);
            $add('Server', 'Cache', Cache::get('health-check') === $v ? 'ok' : 'bad', 'Driver: '.config('cache.default'));
        } catch (\Throwable $e) {
            $add('Server', 'Cache', 'bad', 'Cache not working', 'Check CACHE_STORE in .env.');
        }
        $writable = is_writable(storage_path('logs')) && is_writable(storage_path('framework'));
        $add('Server', 'Storage', $writable ? 'ok' : 'bad', $writable ? 'storage/ is writable' : 'storage/ is not writable', $writable ? null : 'Give the web server write access to storage/.');
        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());
        if ($free !== false && $total) {
            $pct = round(100 * $free / $total);
            $add('Server', 'Disk space', $pct < 5 ? 'bad' : ($pct < 15 ? 'warn' : 'ok'), self::bytes($free).' free ('.$pct.'%)', $pct < 15 ? 'Free up space on the server.' : null);
        }
        $errors = self::recentErrors();
        $add('Server', 'Errors in the last 24 hours', $errors > 20 ? 'bad' : ($errors ? 'warn' : 'ok'), $errors ? $errors.' logged in storage/logs' : 'None logged', $errors ? 'Read storage/logs/laravel.log.' : null);
        $failed = DB::table('failed_jobs')->count();
        $add('Server', 'Failed background jobs', $failed ? 'warn' : 'ok', $failed ? $failed.' failed' : 'None', $failed ? 'Sheriff → Jobs shows them.' : null);

        // Settings
        $prod = app()->isProduction();
        $add('Settings', 'Debug mode', config('app.debug') ? ($prod ? 'bad' : 'warn') : 'ok', config('app.debug') ? 'On: visitors would see error details' : 'Off', config('app.debug') ? 'Set APP_DEBUG=false in .env on the live site.' : null);
        $https = str_starts_with((string) config('app.url'), 'https://');
        $add('Settings', 'HTTPS', $https ? 'ok' : ($prod ? 'bad' : 'warn'), 'APP_URL is '.config('app.url'), $https ? null : 'Use an https:// APP_URL on the live site.');
        $mail = ! in_array(config('mail.default'), ['log', 'array'], true);
        $add('Settings', 'Email delivery', $mail ? 'ok' : 'warn', 'MAIL_MAILER is '.config('mail.default'), $mail ? null : 'Emails from the website (password resets, the contact form) are only logged.');
        foreach (['stripe' => 'Payments at sign-up and in My Account', 'experian' => 'Credit checks at sign-up'] as $key => $used) {
            $i = config('admin.integrations.'.$key);
            $ready = collect($i['env'])->every(fn ($v) => filled($v));
            $add('Settings', $i['name'], $ready ? 'ok' : 'warn', $ready ? 'Set up in .env' : 'Not set up in .env', $ready ? null : $used.' can\'t work until it is.');
        }

        // Content
        $pages = Page::count();
        $drafts = Page::where('status', '!=', 'Published')->count();
        $add('Content', 'Pages', 'ok', ($pages - $drafts).' published'.($drafts ? ', '.$drafts.' not published' : ''));
        $noDesc = Page::where('status', 'Published')->whereNull('redirect')->whereNull('file')->where(fn ($q) => $q->whereNull('meta_description')->orWhere('meta_description', ''))->count();
        $add('Content', 'Page descriptions', $noDesc ? 'warn' : 'ok', $noDesc ? $noDesc.' published '.Str::plural('page', $noDesc).' have no meta description' : 'Every published page has one', $noDesc ? 'Search engines show a description under the link; add one in each page\'s settings.' : null);
        $sitemap = public_path('sitemap.xml');
        if (! is_file($sitemap)) {
            $add('Content', 'Sitemap', 'bad', 'public/sitemap.xml is missing', 'Use Rebuild Sitemap on List Pages.');
        } else {
            $changed = Page::where('updated_at', '>', date('Y-m-d H:i:s', filemtime($sitemap)))->count();
            $add('Content', 'Sitemap', $changed ? 'warn' : 'ok', 'Built '.date('n/j/Y g:i A', filemtime($sitemap)).($changed ? '; '.$changed.' '.Str::plural('page', $changed).' changed since' : ''), $changed ? 'Use Rebuild Sitemap on List Pages.' : null);
        }

        // Traffic
        $blockedHour = SiteVisit::where('blocked', true)->where('created_at', '>=', now()->subHour())->count();
        $add('Traffic', 'Blocked requests, last hour', $blockedHour > 100 ? 'warn' : 'ok', $blockedHour.' turned away', $blockedHour > 100 ? 'Someone keeps trying; see Visitors.' : null);
        $lastView = SiteVisit::where('blocked', false)->max('created_at');
        $quiet = ! $lastView || now()->diffInHours($lastView, true) > 6;
        $add('Traffic', 'Last page view', $quiet ? 'warn' : 'ok', $lastView ? Carbon::parse($lastView)->diffForHumans() : 'None yet', $quiet ? 'No visits for hours: check that the site is up.' : null);

        return $checks;
    }

    private static function recentErrors(): int
    {
        $log = storage_path('logs/laravel.log');
        if (! is_file($log)) {
            return 0;
        }
        // Only the tail: the log can be large
        $fh = fopen($log, 'r');
        fseek($fh, max(0, filesize($log) - 512 * 1024));
        $tail = stream_get_contents($fh);
        fclose($fh);
        preg_match_all('/^\[(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d)\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY):/m', $tail, $m);
        $since = now()->subDay()->format('Y-m-d H:i:s');

        return collect($m[1])->filter(fn ($at) => $at >= $since)->count();
    }

    private static function bytes(float $b): string
    {
        return $b >= 1 << 30 ? round($b / (1 << 30), 1).' GB' : round($b / (1 << 20)).' MB';
    }
}
