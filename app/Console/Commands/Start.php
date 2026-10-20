<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

/**
 * php artisan start — everything needed to get the website and admin running, then serves it.
 *
 *   1. .env from .env.example and an app key, if missing
 *   2. the SQLite database file, if that's the connection and it doesn't exist
 *   3. migrations, and the sample data (db:seed) when the database is empty (or --fresh)
 *   4. storage folders and the public/storage link, cleared caches
 *   5. a queue worker (background reports, emails) and the scheduler (crons) in the background
 *   6. php artisan serve (Ctrl+C stops everything)
 */
class Start extends Command
{
    protected $signature = 'start
        {--fresh : Wipe the database and reseed the sample data}
        {--host=127.0.0.1 : Address to serve on}
        {--port=8000 : Port to serve on}
        {--no-serve : Set everything up but don\'t start the server}
        {--no-worker : Don\'t start the queue worker and scheduler}';

    protected $description = 'Set up the database, sample data and storage, then start the queue worker, scheduler and web server';

    /** @var Process[] */
    private array $background = [];

    public function handle(): int
    {
        $this->components->info('Getting '.config('app.name').' ready');

        // 0. PHP extensions: stop early with the fix, rather than failing halfway through seeding
        $need = ['pdo_sqlite' => 'the SQLite database', 'mbstring' => 'text handling', 'openssl' => 'encryption', 'fileinfo' => 'checking uploaded file types'];
        $nice = ['zlib' => 'smaller Excel and zip files', 'curl' => 'API connection tests'];
        $missing = array_filter($need, fn ($why, $ext) => ! extension_loaded($ext), ARRAY_FILTER_USE_BOTH);
        foreach (array_filter($nice, fn ($why, $ext) => ! extension_loaded($ext), ARRAY_FILTER_USE_BOTH) as $ext => $why) {
            $this->components->warn("PHP extension \"$ext\" isn't on ($why). Optional: turn it on in ".(php_ini_loaded_file() ?: 'php.ini').' with extension='.$ext);
        }
        if ($missing) {
            foreach ($missing as $ext => $why) {
                $this->components->error("PHP extension \"$ext\" is needed for $why.");
            }
            $this->line('  Turn '.(count($missing) > 1 ? 'them' : 'it').' on in '.(php_ini_loaded_file() ?: 'your php.ini').' by removing the ; in front of: '.implode(', ', array_map(fn ($e) => 'extension='.$e, array_keys($missing))).', then run this again.');

            return self::FAILURE;
        }

        // 1. .env and app key
        if (! File::exists(base_path('.env'))) {
            File::copy(base_path('.env.example'), base_path('.env'));
            $this->components->task('Created .env from .env.example');
        }
        if (blank(config('app.key')) && ! preg_match('/^APP_KEY=\S+/m', File::get(base_path('.env')))) {
            $this->components->task('Generated the app key', fn () => $this->callSilently('key:generate', ['--force' => true]) === 0);
        }

        // 2. SQLite database file
        if (config('database.default') === 'sqlite') {
            $db = config('database.connections.sqlite.database');
            if ($db && $db !== ':memory:' && ! File::exists($db)) {
                File::ensureDirectoryExists(dirname($db));
                File::put($db, '');
                $this->components->task('Created the SQLite database ('.basename($db).')');
            }
        }

        // 3. Migrations and sample data
        if ($this->option('fresh')) {
            $this->components->task('Rebuilt the database with sample data (migrate:fresh --seed)', fn () => $this->callSilently('migrate:fresh', ['--seed' => true, '--force' => true]) === 0);
        } else {
            $this->components->task('Ran migrations', fn () => $this->callSilently('migrate', ['--force' => true]) === 0);
            if (! Schema::hasTable('users') || DB::table('users')->count() === 0) {
                $this->components->task('Loaded the sample data (db:seed)', fn () => $this->callSilently('db:seed', ['--force' => true]) === 0);
            } else {
                $this->components->twoColumnDetail('Sample data', 'already loaded (use --fresh to reset)');
            }
        }

        // 4. Storage and caches
        foreach (['app/public', 'app/reports', 'app/files', 'framework/cache', 'framework/sessions', 'framework/views', 'logs'] as $dir) {
            File::ensureDirectoryExists(storage_path($dir));
        }
        if (! File::exists(public_path('storage'))) {
            $this->components->task('Linked public/storage', fn () => $this->callSilently('storage:link') === 0);
        }
        $this->components->task('Cleared caches', fn () => $this->callSilently('optimize:clear') === 0);

        if ($this->option('no-serve')) {
            $this->components->info('Ready. Run php artisan start (or php artisan serve) to open it.');

            return self::SUCCESS;
        }

        // 5. Background worker and scheduler
        if (! $this->option('no-worker')) {
            $this->background[] = $this->spawn(['queue:work', '--tries=1', '--sleep=1'], 'Queue worker (background reports, emails)');
            $this->background[] = $this->spawn(['schedule:work'], 'Scheduler (crons)');
            register_shutdown_function(fn () => $this->stopBackground());
            if (function_exists('pcntl_async_signals')) {
                pcntl_async_signals(true);
                foreach ([SIGINT, SIGTERM] as $sig) {
                    pcntl_signal($sig, function () {
                        $this->stopBackground();
                        exit(0);
                    });
                }
            }
        }

        // 6. Web server
        $url = 'http://'.$this->option('host').':'.$this->option('port');
        $this->newLine();
        $this->components->twoColumnDetail('Website', $url);
        $this->components->twoColumnDetail('Admin', $url.'/admin  (admin@example.com / password)');
        $this->components->twoColumnDetail('My Account', $url.'/myaccount  (demo.customer / password)');
        $this->newLine();

        $code = $this->call('serve', ['--host' => $this->option('host'), '--port' => $this->option('port')]);
        $this->stopBackground();

        return $code;
    }

    private function spawn(array $args, string $label): Process
    {
        $p = new Process([PHP_BINARY, base_path('artisan'), ...$args], base_path(), null, null, null);
        $p->start();
        $this->components->twoColumnDetail($label, 'running (pid '.$p->getPid().')');

        return $p;
    }

    private function stopBackground(): void
    {
        foreach ($this->background as $p) {
            if ($p->isRunning()) {
                $p->stop(3);
            }
        }
        $this->background = [];
    }
}
