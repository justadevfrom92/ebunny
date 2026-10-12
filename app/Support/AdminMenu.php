<?php

namespace App\Support;

use App\Models\ContactLog;
use App\Models\ContactMessage;
use App\Models\JobRun;
use App\Models\LedgerEntry;
use App\Models\Refund;
use App\Models\ReportRun;
use App\Models\StarEntry;
use App\Models\WorkItem;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/** Builds an app's sidebar (from config('admin.apps.<key>.menu') or a custom app's links) for the admin layout. */
class AdminMenu
{
    /** App key of the current page, from the route name ("corral.customers.show" → "corral"). */
    public static function currentApp(): ?string
    {
        if (Route::currentRouteName() === 'custom.show') {
            return request()->route('appKey');
        }
        $key = Str::before((string) Route::currentRouteName(), '.');

        return config()->has('admin.apps.'.$key) ? $key : null;
    }

    /** @return array<string, array<int, array{label: string, url: string, active: bool, badge: int|null}>> */
    public static function sections(string $app): array
    {
        $current = (string) Route::currentRouteName();
        $sections = [];

        // Apps created from the launcher: plain links, none of them is the current page
        if (! config()->has("admin.apps.$app")) {
            foreach (AdminApps::get($app)['menu'] ?? [] as $heading => $items) {
                foreach ($items as $it) {
                    $sections[$heading][] = $it + ['active' => false, 'badge' => null];
                }
            }

            return $sections;
        }

        foreach (config("admin.apps.$app.menu") as $heading => $items) {
            if ($items === 'reference_tables') {
                $items = collect(config('admin.reference_tables'))->filter(fn ($t) => ($t[2] ?? 'sheriff') === $app)
                    ->map(fn ($t, $key) => [$t[0], $app.'.data.show', null, ['table' => $key]])->sortBy(0)->values()->all();
            }
            if ($items === 'integrations') {
                $items = [['All APIs', 'sheriff.integrations.index'], ...collect(config('admin.integrations'))
                    ->map(fn ($i, $key) => [$i['name'], 'sheriff.integrations.show', null, ['integration' => $key]])->values()->all()];
            }
            if ($items === 'queues') {
                $items = [['All Exceptions', 'corral.queues.index', 'queues'], ...collect(config('admin.queues'))
                    ->map(fn ($q, $key) => [$q[0], 'corral.queues.show', 'queue:'.$key, ['queue' => $key]])->values()->all()];
            }
            foreach ($items as $item) {
                $params = $item[3] ?? [];
                $sections[$heading][] = [
                    'label' => $item[0],
                    'route' => $item[1],
                    'url' => route($item[1], $params),
                    'exact' => $current === $item[1] && collect($params)->every(fn ($v, $k) => (string) request()->route($k) === (string) $v),
                    'badge' => isset($item[2]) ? self::badge($item[2]) : null,
                ];
            }
        }

        // Highlight the exact item, or else the item from the same area (customers.show → customers.index)
        $hasExact = collect($sections)->flatten(1)->contains('exact', true);
        $area = Str::beforeLast($current, '.');
        $area = config('admin.menu_parents', [])[$area] ?? $area; // pages whose list lives under another name
        // (or rodeo.templates.edit → rodeo.templates); only the first match is highlighted
        $found = false;
        foreach ($sections as &$items) {
            foreach ($items as &$it) {
                $it['active'] = ! $found && ($hasExact ? $it['exact']
                    : (Str::beforeLast($it['route'], '.') === $area && Str::endsWith($it['route'], '.index')) || $it['route'] === $area);
                $found = $found || $it['active'];
            }
        }

        return $sections;
    }

    public static function badge(string $key): ?int
    {
        $n = match (true) {
            str_starts_with($key, 'queue:') => self::queueCounts()[substr($key, 6)] ?? 0,
            $key === 'queues' => WorkItem::open()->count(),
            $key === 'sms' => self::unansweredTexts(),
            $key === 'messages' => ContactMessage::open()->count(),
            $key === 'failed_jobs' => self::failedJobs(),
            $key === 'report_problems' => ReportRun::inState('problem')->count(),
            $key === 'ledger_pending' => LedgerEntry::where('status', 'pending')->count(),
            $key === 'refunds_pending' => Refund::whereIn('status', ['requested', 'approved'])->count(),
            $key === 'redemptions_open' => StarEntry::whereNotNull('reward_offer_id')->where('fulfillment', 'pending')->count(),
            default => 0,
        };

        return $n ?: null;
    }

    /** Open work items per queue, counted once per request. */
    private static function queueCounts(): array
    {
        return once(fn () => WorkItem::open()->selectRaw('queue, count(*) as n')->groupBy('queue')->pluck('n', 'queue')->all());
    }

    /** Customers whose latest text is from them (waiting on a reply). */
    public static function unansweredTexts(): int
    {
        $latest = ContactLog::where('channel', 'SMS')->selectRaw('max(id)')->groupBy('customer_id');

        return ContactLog::whereIn('id', $latest)->where('direction', 'in')->count();
    }

    /** Number of jobs whose most recent run failed. */
    public static function failedJobs(): int
    {
        return collect(array_keys(config('admin.jobs')))
            ->filter(fn ($cmd) => JobRun::where('command', $cmd)->latest('started_at')->value('status') === 'Failed')
            ->count();
    }
}
