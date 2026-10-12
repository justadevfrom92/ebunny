<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\ContentBlock;
use App\Models\Page;
use App\Models\Site;
use App\Support\SiteContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Website pages built in Lando, their AMP versions, and the components shown on the built-in pages. */
class SitePageController extends Controller
{
    private function find(Request $request, string $path): Page
    {
        $path = trim($path, '/') ?: '/';
        abort_if(in_array(Str::before($path, '/'), config('cms.reserved'), true), 404);

        $site = Site::forHost($request->getHost());
        $page = $site?->pages()->with(['template', 'site', 'copyFrom', 'market', 'pricegridGroup'])->where('path', $path)->first();
        // Drafts are visible to signed-in admin users only ("View Page Live" from Lando)
        abort_unless($page && ($page->status === 'Published' || $request->user()), 404);

        return $page;
    }

    public function show(Request $request, string $path)
    {
        $page = $this->find($request, $path);
        if ($page->redirect) {
            return redirect($page->redirect, 301);
        }
        if ($page->file) {
            return response()->file(public_path('frontend/'.$page->file));
        }

        // Hard Cache: keep the finished HTML; it refreshes whenever the page or any content block is saved (or after an hour)
        if ($page->hard_cache && $page->status === 'Published') {
            $key = 'page:'.$page->id.':'.$page->updated_at?->timestamp.':'.$page->contentSource()->updated_at?->timestamp.':'.ContentBlock::max('updated_at');

            return response(Cache::remember($key, 3600, fn () => $this->render($page)))->header('X-Page-Cache', 'hard');
        }

        return response($this->render($page));
    }

    /** /amp/<path>: the page's AMP Content as an AMP page. */
    public function amp(Request $request, string $path)
    {
        $page = $this->find($request, $path);
        $source = $page->contentSource();
        $amp = $source->areaBlock('amp');
        abort_if($page->redirect || $page->file || ! $amp, 404);

        return response()->view('frontend.amp', ['page' => $page, 'site' => $page->site, 'content' => SiteContent::expand($amp->html, $page->site)]);
    }

    private function render(Page $page): string
    {
        $site = $page->site;
        $source = $page->contentSource();
        SiteContent::$addressBoxTitle = $page->address_box_title;

        return view('frontend.page', [
            'page' => $page,
            'site' => $site,
            'title' => $source->page_title ?: $page->title,
            'content' => collect(['primary', 'secondary', 'parent', 'auxiliary'])
                ->mapWithKeys(fn ($area) => [$area => SiteContent::expand($source->areaBlock($area)?->html, $site)])->all(),
            'hasAmp' => (bool) $source->content_amp_id,
            'grid' => SiteContent::priceGrid($page),
            'zones' => SiteContent::zones($page),
        ])->render();
    }

    /** /site/components?page=plans.html — components for a built-in page's zones. */
    public function components(Request $request): JsonResponse
    {
        $file = basename((string) $request->query('page')) ?: 'index.html';
        $page = Site::forHost($request->getHost())?->pages()->with('site')->where('file', $file)->where('status', 'Published')->first();
        SiteContent::$addressBoxTitle = $page?->address_box_title;

        return response()->json($page ? SiteContent::zones($page) : (object) []);
    }
}
