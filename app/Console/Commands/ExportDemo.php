<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

/**
 * Writes the pages listed in config/demo.php (and any --page) as a static site that works from any
 * folder, such as a GitHub Pages project site: each page becomes an .html file, links between the
 * chosen pages point at each other, and links to every other page are switched off.
 */
class ExportDemo extends Command
{
    protected $signature = 'demo:export {--page=* : Extra pages to include, e.g. /admin/ranger/tickets/12} {--out= : Folder to write to (default config demo.output)} {--keep-brand : Don\'t swap in the placeholder brand}';

    protected $description = 'Export chosen pages as a static demo site (e.g. for GitHub Pages)';

    /** Folders under public/ the pages need (styles, scripts, images). */
    private const ASSETS = ['backend', 'frontend', 'shared'];

    /** Scripts the app writes from config and the database, which shared/boot.js loads. */
    private const GENERATED = ['shared/config/brand.js', 'shared/config/catalog.js'];

    /** @var array<string, string> page key => file path (relative to the output folder) */
    private array $pages = [];

    /** @var array<string, string> internal file URL (images, PDFs) => saved file */
    private array $files = [];

    private string $out;

    private string $root;

    public function handle(): int
    {
        $this->out = rtrim($this->option('out') ?: base_path(config('demo.output', 'build/demo')), '/');
        $this->root = rtrim((string) config('app.url'), '/');
        $list = array_values(array_unique([...config('demo.pages', []), ...$this->option('page')]));
        if (! $list) {
            $this->error('No pages to export. List them in config/demo.php.');

            return self::FAILURE;
        }

        foreach ($list as $p) {
            $this->pages[$this->key($p)] = $this->fileFor($p);
        }
        File::deleteDirectory($this->out);
        File::ensureDirectoryExists($this->out);

        $failed = 0;
        foreach ($list as $p) {
            [$status, $html] = $this->fetch($p);
            if ($status !== 200 || ! str_contains((string) $html, '<')) {
                $this->warn("  skipped {$p} (HTTP {$status})");
                $failed++;

                continue;
            }
            $file = $this->pages[$this->key($p)];
            File::ensureDirectoryExists(dirname($this->out.'/'.$file));
            File::put($this->out.'/'.$file, $this->rewrite($html, $file));
            $this->line("  {$p} → {$file}");
        }

        foreach (self::ASSETS as $dir) {
            if (is_dir(public_path($dir))) {
                File::copyDirectory(public_path($dir), $this->out.'/'.$dir);
            }
        }
        foreach (self::GENERATED as $path) {
            $this->save('/'.$path, $path);
        }
        if (! isset($this->pages[$this->key('/')])) {
            // GitHub Pages opens index.html: send it to the first page
            $first = $this->pages[$this->key($list[0])];
            File::put($this->out.'/index.html', '<!doctype html><meta charset="utf-8"><meta http-equiv="refresh" content="0; url='.e($first).'"><a href="'.e($first).'">Open the demo</a>');
        }
        if (! $this->option('keep-brand') && $swap = config('demo.replace', [])) {
            foreach (File::allFiles($this->out) as $f) {
                if (in_array($f->getExtension(), ['html', 'js', 'css', 'svg'], true)) {
                    File::put($f->getPathname(), strtr(File::get($f->getPathname()), $swap));
                }
            }
        }
        File::put($this->out.'/.nojekyll', '');   // GitHub Pages: serve folders that start with _ as they are

        $this->info(count($list) - $failed.' pages and '.count($this->files).' generated files written to '.$this->out.'. Open index.html, or publish the folder (e.g. GitHub Pages).');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** "/admin/ranger/tickets?view=unassigned" → "admin/ranger/tickets?view=unassigned" (no host, no trailing slash, sorted query). */
    private function key(string $url): ?string
    {
        $url = preg_replace('#^'.preg_quote($this->root, '#').'#', '', html_entity_decode($url));
        if (preg_match('#^(https?:)?//#', $url) || ! str_starts_with($url, '/')) {
            return null;   // another site, or not a path
        }
        // "/plans.html", "/plans/" and "/plans" are the same page
        $path = preg_replace('#(^|/)index$#', '', preg_replace('/\.html$/', '', trim((string) parse_url($url, PHP_URL_PATH), '/')));
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        ksort($q);

        return $path.($q ? '?'.http_build_query($q) : '');
    }

    /** Where a page is written: "admin/ranger/index.html", or "admin/ranger/tickets/index--view-unassigned.html" with a query. */
    private function fileFor(string $url): string
    {
        [$path, $query] = array_pad(explode('?', (string) $this->key($url), 2), 2, '');
        $name = 'index'.($query !== '' ? '--'.trim(preg_replace('/[^a-z0-9]+/i', '-', $query), '-') : '').'.html';

        return ltrim($path.'/'.$name, '/');
    }

    /** Render a page in this process, signed in as the demo admin or My Account customer. */
    private function fetch(string $url, bool $raw = false): array
    {
        $path = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        Auth::guard('web')->forgetUser();
        Auth::guard('customer')->forgetUser();
        if (str_starts_with($path, '/admin') && $u = User::where('email', config('demo.admin_user'))->first()) {
            Auth::guard('web')->setUser($u);
        }
        if (str_starts_with($path, '/myaccount') && $c = Customer::where('username', config('demo.customer'))->first()) {
            Auth::guard('customer')->setUser($c);
        }
        $kernel = app(Kernel::class);
        $request = Request::create($this->root.'/'.ltrim($url, '/'));
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        if ($response->isRedirection() && ! $raw) {
            return $this->fetch((string) $response->headers->get('Location'));
        }
        $body = method_exists($response, 'getFile') ? File::get($response->getFile()->getPathname()) : $response->getContent();

        return [$response->getStatusCode(), $body, (string) $response->headers->get('Content-Type')];
    }

    /** Point links at exported pages, switch off the rest, and make asset paths relative to this page. */
    private function rewrite(string $html, string $file): string
    {
        $up = str_repeat('../', substr_count($file, '/'));
        $html = preg_replace_callback('/\s(href|src|action|data-href|data-then|poster)=(["\'])(.*?)\2/i', function ($m) use ($up) {
            [$all, $attr, $q, $url] = $m;
            $attr = strtolower($attr);
            if ($url === '' || $url[0] === '#' || preg_match('/^(mailto|tel|data|javascript):/i', $url)) {
                return $all;
            }
            $key = $this->key($url);
            if ($key === null) {
                return $all;   // another website (fonts, maps): leave it
            }
            $frag = str_contains($url, '#') ? '#'.explode('#', $url, 2)[1] : '';
            $first = explode('/', $key)[0];
            if (in_array(explode('?', $first)[0], self::ASSETS, true) || in_array($key, ['favicon.ico', 'robots.txt'], true)) {
                $path = explode('?', $key)[0];
                if (! is_file(public_path($path))) {
                    $this->save($url, $path);   // made by the app (like shared/config/brand.js): save it at the same path
                }

                return " {$attr}={$q}{$up}{$path}{$q}";
            }
            if (isset($this->pages[$key])) {
                return " {$attr}={$q}{$up}{$this->pages[$key]}{$frag}{$q}";
            }
            if (in_array($attr, ['src', 'poster'], true) && $saved = $this->save($url)) {
                return " {$attr}={$q}{$up}{$saved}{$q}";   // an image or PDF from the app
            }

            // A page that isn't part of the demo: no link
            return $attr === 'action' ? " action={$q}#{$q} data-demo-off" : ' data-demo-off';
        }, $html);

        $extra = '<style>[data-demo-off]{cursor:not-allowed!important}a[data-demo-off]{opacity:.6;text-decoration:none!important}tr[data-demo-off]{cursor:default}'
            .'.demo-flag{position:fixed;left:12px;bottom:12px;z-index:9999;background:#14213d;color:#fff;font:600 12px/1.3 system-ui,sans-serif;padding:7px 11px;border-radius:999px;opacity:.88;pointer-events:none}</style>';
        // Links that scripts add later (like the website's menu) are checked when clicked, against the same list
        $map = json_encode(['up' => $up, 'pages' => $this->pages], JSON_UNESCAPED_SLASHES);
        $script = '<div class="demo-flag">Demo · sample data</div><script>(function(){var D='.$map.',files={};for(var k in D.pages)files[D.pages[k]]=1;'
            .'var root=new URL(D.up||"./",location.href).href;'
            .'function key(p,s){var u=new URLSearchParams(s),q=[];u.forEach(function(v,k){q.push([k,v]);});q.sort(function(a,b){return a[0]<b[0]?-1:1;});'
            .'p=p.replace(/^\\/+|\\/+$/g,"").replace(/\\.html$/,"").replace(/(^|\\/)index$/,"");'
            .'return p+(q.length?"?"+q.map(function(x){return encodeURIComponent(x[0])+"="+encodeURIComponent(x[1]);}).join("&"):"");}'
            .'document.addEventListener("submit",function(e){e.preventDefault();e.stopPropagation();alert("This is a demo: nothing is saved.");},true);'
            .'document.addEventListener("click",function(e){var a=e.target.closest&&e.target.closest("a,[data-demo-off]");if(!a)return;'
            .'if(a.hasAttribute("data-demo-off")){e.preventDefault();e.stopPropagation();return;}'
            .'var raw=a.getAttribute("href");if(!raw||raw.charAt(0)==="#"||/^(mailto|tel|javascript):/i.test(raw))return;'
            .'var u=new URL(a.href,location.href),rel;'
            .'if(raw.charAt(0)==="/"&&raw.indexOf("//")!==0)rel=raw.split("?")[0];'
            .'else if(u.href.indexOf(root)===0){rel=u.pathname.slice(new URL(root).pathname.length);if(files[rel])return;}'
            .'else return;'
            .'e.preventDefault();e.stopPropagation();var f=D.pages[key(rel,u.search)];if(f)location.href=root+f+u.hash;},true);})();</script>';
        $html = preg_replace('#</head>#i', $extra.'</head>', $html, 1);

        return preg_replace('#</body>#i', $script.'</body>', $html, 1);
    }

    /** Save an image/PDF the app serves (not a static file) next to the pages. */
    private function save(string $url, ?string $as = null): ?string
    {
        if (isset($this->files[$url])) {
            return $this->files[$url];
        }
        [$status, $body, $type] = $this->fetch($url, true);
        if ($status !== 200 || ($as === null && str_contains($type, 'text/html'))) {
            return null;
        }
        $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/svg+xml' => 'svg', 'application/pdf' => 'pdf'][strtok($type, ';')] ?? 'bin';
        $name = $as ?? '_files/'.substr(md5($url), 0, 12).'.'.$ext;
        File::ensureDirectoryExists(dirname($this->out.'/'.$name));
        File::put($this->out.'/'.$name, $body);

        return $this->files[$url] = $name;
    }
}
