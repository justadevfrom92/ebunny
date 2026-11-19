<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AdminMenu;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Test the whole app from the command line: opens the website's main pages and every admin menu page as an
 * administrator (inside this process, no browser or web server needed) and reports any page that errors.
 * With --records it also follows the links on those pages into the record detail pages (customers, tickets,
 * orders…), opening a few of each kind, and lists the detail pages it found no link to.
 * Run it after a deploy along with `php artisan et:heartbeat`.
 */
class SelfTest extends Command
{
    protected $signature = 'et:self-test {--as=admin@example.com : The admin user to open the admin pages as} {--app= : Only this admin app (e.g. corral, or account for your own pages)} {--records : Also follow the links on those pages into record detail pages (a few of each kind)} {--per=3 : With --records: how many pages of each kind to open}';

    protected $description = 'Self-test: open the website and every admin menu page and report errors';

    public function handle(Kernel $kernel): int
    {
        $user = User::where('email', $this->option('as'))->first();
        if (! $user) {
            $this->error('No admin user '.$this->option('as').'. Pass --as=someone@yourcompany.com');

            return self::FAILURE;
        }
        $pages = collect(['/', '/shop', '/admin/login'])->map(fn ($u) => ['Website', $u, false]);
        foreach (array_keys(config('admin.apps')) as $app) {
            if (($this->option('app') && $this->option('app') !== $app) || ! $user->hasPerm($app)) {
                continue;
            }
            foreach (AdminMenu::sections($app) as $items) {
                foreach ($items as $it) {
                    $pages->push([config("admin.apps.$app.name"), parse_url($it['url'], PHP_URL_PATH).(($q = parse_url($it['url'], PHP_URL_QUERY)) ? '?'.$q : ''), true]);
                }
            }
        }
        // The admin home (launcher) and your own Account area (Notifications, My Files, Settings, My Profile…)
        if (! $this->option('app') || $this->option('app') === 'account') {
            $pages->push(['Admin', '/admin', true]);
            foreach (AdminMenu::sections('account') as $items) {
                foreach ($items as $it) {
                    $pages->push([config('admin.account.name'), parse_url($it['url'], PHP_URL_PATH), true]);
                }
            }
        }

        $rows = [];
        $bad = 0;
        $seen = [];         // paths already opened
        $perRoute = [];     // route uri => pages opened for it
        $routes = app('router')->getRoutes();
        $routeOf = function (string $path) use ($routes): ?string {
            try {
                return $routes->match(Request::create($path, 'GET'))->uri();
            } catch (\Throwable) {
                return null;
            }
        };
        $open = function (array $p) use ($kernel, $user, &$rows, &$bad, &$seen, &$perRoute, $routeOf): string {
            [$area, $path, $signedIn] = $p;
            $seen[$path] = true;
            $uri = $routeOf($path);
            if ($uri) {
                $perRoute[$uri] = ($perRoute[$uri] ?? 0) + 1;
            }
            auth()->guard()->forgetUser();
            if ($signedIn) {
                auth()->setUser($user);
            }
            $t = microtime(true);
            $html = '';
            try {
                $res = $kernel->handle(Request::create($path, 'GET'));
                $code = $res->getStatusCode();
                $note = $res->isServerError() ? (string) str($res->exception?->getMessage() ?? '')->limit(90) : '';
                $type = (string) $res->headers->get('Content-Type');
                if ($code === 200 && str_contains($type, 'html') && ! ($res instanceof BinaryFileResponse) && ! ($res instanceof StreamedResponse)) {
                    $html = (string) $res->getContent();
                }
            } catch (\Throwable $e) {
                [$code, $note] = [500, (string) str($e->getMessage())->limit(90)];
            }
            // A sample record (pages nothing linked to) can be someone else's or another record's child: "not found" is right then
            $skipped = $area === 'Unlinked' && in_array($code, [403, 404], true);
            $failed = $code >= 500 || ($code === 404 && ! $skipped);
            $bad += $failed ? 1 : 0;
            $rows[] = [$area, $path, $code, (int) round((microtime(true) - $t) * 1000).' ms', $failed ? 'FAIL '.$note : ($skipped ? 'skipped (sample record not open to this person)' : 'ok')];

            return $html;
        };

        $queue = $pages->unique(1)->values()->all();
        $this->info('Opening '.count($queue).' menu pages…');
        $found = [];        // links found on the menu pages: [area, path]
        $this->withProgressBar($queue, function ($p) use ($open, &$found) {
            $html = $open($p);
            if ($this->option('records') && $p[2]) {
                foreach ($this->links($html) as $link) {
                    $found[] = [$p[0], $link];
                }
            }
        });
        $this->newLine();

        if ($this->option('records')) {
            // Follow links three levels deep (list → record → its own pages → theirs), a few pages of each kind
            $per = max(1, (int) $this->option('per'));
            for ($depth = 1; $depth <= 3 && $found; $depth++) {
                $next = [];
                $pending = [];   // pages of each kind picked in this level
                $batch = collect($found)->unique(1)->filter(function ($f) use (&$seen, &$perRoute, &$pending, $routeOf, $per) {
                    $uri = isset($seen[$f[1]]) ? null : $routeOf($f[1]);
                    if (! $uri || ($perRoute[$uri] ?? 0) + ($pending[$uri] ?? 0) >= $per) {
                        return false;
                    }
                    $pending[$uri] = ($pending[$uri] ?? 0) + 1;
                    $seen[$f[1]] = true;

                    return true;
                })->values();
                $this->info('Opening '.$batch->count().' linked pages (level '.$depth.')…');
                $this->withProgressBar($batch, function ($f) use ($open, &$next) {
                    $html = $open([$f[0], $f[1], true]);
                    foreach ($this->links($html) as $link) {
                        $next[] = [$f[0], $link];
                    }
                });
                $this->newLine();
                $found = $next;
            }
        }

        $missed = collect();
        if ($this->option('records')) {
            // Detail pages nothing linked to: open them with a sample record for each {record} in the address
            $notReached = function () use ($routes, &$perRoute) {
                return collect($routes->getRoutes())->filter(fn ($r) => in_array('GET', $r->methods(), true) && str_starts_with($r->uri(), 'admin/') && str_contains($r->uri(), '{')
                    && ! isset($perRoute[$r->uri()]) && ! preg_match('#(download|export|login|logout|preview|print|pdf$|notifications$)#', $r->uri().' '.$r->getName()));
            };
            $direct = $notReached()->map(fn ($r) => $this->sampleUrl($r))->filter()->values();
            if ($direct->isNotEmpty()) {
                $this->info('Opening '.$direct->count().' pages nothing linked to, with a sample record…');
                $this->withProgressBar($direct, fn ($path) => $open(['Unlinked', $path, true]));
                $this->newLine();
            }
            $missed = $notReached()->map(fn ($r) => $r->uri())->values();
        }

        $this->newLine();
        $this->table(['Area', 'Page', 'Status', 'Time', 'Result'], $rows);
        $this->line(count($rows).' pages opened · '.($bad ? "<error>$bad failed</error>" : '<info>all OK</info>'));

        if ($this->option('records')) {
            $this->line(count($perRoute).' kinds of page opened · '.$missed->count().' kinds not opened (no record to open them with, or the address needs a name rather than a record)');
            if ($missed->isNotEmpty() && $this->output->isVerbose()) {
                $missed->each(fn ($u) => $this->line('  · /'.$u));
            }
        }

        return $bad ? self::FAILURE : self::SUCCESS;
    }

    /** The route's address filled in with the first record of each {record} it takes, or null if a part isn't a record. */
    private function sampleUrl(Route $route): ?string
    {
        $params = [];
        foreach ($route->signatureParameters(['subClass' => Model::class]) as $param) {
            $class = $param->getType()?->getName();
            $record = $class ? $class::query()->orderBy((new $class)->getKeyName())->first() : null;
            if (! $record) {
                return null;
            }
            $params[$param->getName()] = $record;
        }
        if (array_diff($route->parameterNames(), array_keys($params))) {
            return null;
        }
        try {
            return parse_url(route($route->getName(), $params), PHP_URL_PATH);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Admin links on a page (href and data-href), without the ones that change things or download files. */
    private function links(string $html): array
    {
        preg_match_all('#(?:href|data-href)="([^"]+)"#', $html, $m);

        return collect($m[1])->map(fn ($u) => html_entity_decode($u))
            ->map(fn ($u) => preg_replace('#^https?://[^/]+#', '', $u))
            ->filter(fn ($u) => str_starts_with($u, '/admin') && ! str_contains($u, '#'))
            ->reject(fn ($u) => preg_match('#(logout|login|download|export|/run\b|[?&](run|format|as)=|switch|return|impersonat|\.(csv|xlsx|pdf|zip|txt)$)#i', $u))
            ->map(fn ($u) => preg_replace('#[?&]page=\d+#', '', $u))
            ->unique()->values()->all();
    }
}
