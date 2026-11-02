<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AdminMenu;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

/**
 * Test the whole app from the command line: opens the website's main pages and every admin menu page as an
 * administrator (inside this process, no browser or web server needed) and reports any page that errors.
 * Run it after a deploy along with `php artisan et:heartbeat`.
 */
class SelfTest extends Command
{
    protected $signature = 'et:self-test {--as=admin@example.com : The admin user to open the admin pages as} {--app= : Only this admin app (e.g. corral)}';

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

        $rows = [];
        $bad = 0;
        $this->withProgressBar($pages->unique(1)->values(), function ($p) use ($kernel, $user, &$rows, &$bad) {
            [$area, $path, $signedIn] = $p;
            auth()->guard()->forgetUser();
            if ($signedIn) {
                auth()->setUser($user);
            }
            $t = microtime(true);
            try {
                $res = $kernel->handle(Request::create($path, 'GET'));
                $code = $res->getStatusCode();
                $note = $res->isServerError() ? (string) str($res->exception?->getMessage() ?? '')->limit(90) : '';
            } catch (\Throwable $e) {
                [$code, $note] = [500, (string) str($e->getMessage())->limit(90)];
            }
            if ($code >= 500 || $code === 404) {
                $bad++;
            }
            $rows[] = [$area, $path, $code, (int) round((microtime(true) - $t) * 1000).' ms', $code >= 500 || $code === 404 ? 'FAIL '.$note : 'ok'];
        });
        $this->newLine(2);
        $this->table(['Area', 'Page', 'Status', 'Time', 'Result'], $rows);
        $this->line(count($rows).' pages opened · '.($bad ? "<error>$bad failed</error>" : '<info>all OK</info>'));

        return $bad ? self::FAILURE : self::SUCCESS;
    }
}
