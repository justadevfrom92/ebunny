<?php

namespace App\Http\Controllers\Backend\Lando;

use App\Http\Controllers\Controller;
use App\Models\ContentBlock;
use App\Models\HistoryItem;
use App\Models\Market;
use App\Models\Page;
use App\Models\PageComponent;
use App\Models\PlanGroup;
use App\Models\Site;
use App\Models\SiteBlock;
use App\Models\SiteVisit;
use App\Models\Template;
use App\Support\SiteTraffic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Website pages (Lando → Pages). Each page belongs
 * to a site, has content areas and components, and is served at its path.
 */
class PageController extends Controller
{
    private function site(Request $request): Site
    {
        return Site::find($request->integer('site')) ?? Site::orderBy('id')->firstOrFail();
    }

    /** Every page of the site as a table, by path (sub-pages sit under their parent). */
    public function index(Request $request): View
    {
        $site = $this->site($request);
        $pages = $site->pages()->with('template')->withCount('components')->orderBy('path')->get();

        // Per page: views in the last 7 days, visitors on it now, and admins editing it
        $ids = $site->pages()->pluck('id');
        $traffic = [
            'views' => SiteVisit::whereIn('page_id', $ids)->where('blocked', false)->where('created_at', '>=', today()->subDays(6))->selectRaw('page_id, count(*) as n')->groupBy('page_id')->pluck('n', 'page_id'),
            'online' => SiteVisit::whereIn('page_id', $ids)->online()->where('blocked', false)->selectRaw('page_id, count(distinct visitor) as n')->groupBy('page_id')->pluck('n', 'page_id'),
            'editing' => SiteTraffic::editing(),
            'blocked' => SiteBlock::where('type', 'area')->inForce()->get(),
        ];

        return view('backend.lando.pages.index', ['site' => $site, 'sites' => Site::orderBy('name')->get(), 'pages' => $pages, 'traffic' => $traffic]);
    }

    public function create(Request $request): View
    {
        return $this->form(new Page(['status' => 'Draft', 'site_id' => $this->site($request)->id, 'canonical' => true]));
    }

    public function store(Request $request): RedirectResponse
    {
        $page = Page::create($this->validated($request));

        return redirect(app_route('pages.edit', $page))->with('status', 'Page created. Add components below, then publish it.');
    }

    public function edit(Request $request, Page $page): View
    {
        // Shown on List Pages and the page's Activity as "being edited by"
        SiteTraffic::editing($page->id, ['id' => $request->user()->id, 'name' => $request->user()->name]);

        return $this->form($page);
    }

    /** Lando → List Pages → Activity: who is on the page now, its views, and its change history. */
    public function activity(Page $page): View
    {
        $visits = SiteVisit::where('page_id', $page->id);
        $since = today()->subDays(29);
        $perDay = (clone $visits)->where('blocked', false)->where('created_at', '>=', $since)->get(['created_at'])->countBy(fn ($v) => $v->created_at->toDateString());

        return view('backend.lando.pages.activity', [
            'page' => $page->load('site', 'template'),
            'online' => (clone $visits)->online()->where('blocked', false)->with('customer')->latest('seen_at')->get()->unique('visitor'),
            'editing' => SiteTraffic::editing($page->id),
            'stats' => [
                'today' => (clone $visits)->where('blocked', false)->where('created_at', '>=', today())->count(),
                'week' => (clone $visits)->where('blocked', false)->where('created_at', '>=', today()->subDays(6))->count(),
                'month' => $perDay->sum(),
                'people' => (clone $visits)->where('blocked', false)->where('created_at', '>=', $since)->distinct()->count('visitor'),
                'blocked' => (clone $visits)->where('blocked', true)->where('created_at', '>=', $since)->count(),
            ],
            'days' => collect(range(29, 0))->map(fn ($i) => [$d = today()->subDays($i), $perDay[$d->toDateString()] ?? 0])->all(),
            'referrers' => (clone $visits)->whereNotNull('referrer')->where('created_at', '>=', $since)->get(['referrer'])
                ->countBy(fn ($v) => parse_url($v->referrer, PHP_URL_HOST) ?: $v->referrer)->sortDesc()->take(6),
            'recent' => (clone $visits)->with('customer')->latest()->limit(15)->get(),
            'history' => HistoryItem::with('user')->where(fn ($q) => $q->where('model', 'Page_model')->where('record_id', $page->id)
                ->orWhere(fn ($w) => $w->where('model', 'PageComponent_model')->where('data->page_id', $page->id)))
                ->latest('created_at')->latest('id')->paginate(25),
            'blocks' => SiteBlock::where('type', 'area')->get()->filter(fn ($b) => $b->matchesPath($page->path)),
        ]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $page->update($this->validated($request, $page));

        return redirect(app_route('pages.edit', $page))->with('status', 'Page saved');
    }

    /** Needs the "delete" right. */
    public function destroy(Page $page): RedirectResponse
    {
        Gate::authorize('delete');
        abort_if($page->file !== null, 422, 'Built-in pages can\'t be deleted.');
        $site = $page->site_id;
        $page->delete();

        return redirect(app_route('pages.index', ['site' => $site]))->with('status', 'Page deleted');
    }

    // ---------- Page components ----------

    public function addComponent(Request $request, Page $page): RedirectResponse
    {
        $data = $request->validate([
            'content_block_id' => ['required', Rule::exists('content_blocks', 'id')->where(fn ($q) => $q->whereNull('site_id')->orWhere('site_id', $page->site_id))],
            'zone' => ['required', Rule::in(array_keys(config('cms.zones')))],
        ]);
        abort_if($page->file && ! in_array($data['zone'], ['top', 'bottom'], true), 422, 'Built-in pages only have top and bottom zones.');
        $data['position'] = (int) $page->components()->where('zone', $data['zone'])->max('position') + 1;
        $page->components()->create($data);

        return redirect(app_route('pages.edit', $page).'#components')->with('status', 'Component added');
    }

    public function moveComponent(Request $request, PageComponent $component): RedirectResponse
    {
        $dir = $request->validate(['dir' => ['required', Rule::in(['up', 'down'])]])['dir'];
        $siblings = PageComponent::where('page_id', $component->page_id)->where('zone', $component->zone)->orderBy('position')->orderBy('id')->get()->values();
        $i = $siblings->search(fn ($c) => $c->id === $component->id);
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if (isset($siblings[$j])) {
            $order = $siblings->all();
            [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
            foreach ($order as $pos => $c) {
                $c->update(['position' => $pos]);
            }
        }

        return redirect(app_route('pages.edit', $component->page_id).'#components');
    }

    public function removeComponent(PageComponent $component): RedirectResponse
    {
        $page = $component->page_id;
        $component->delete();

        return redirect(app_route('pages.edit', $page).'#components')->with('status', 'Component removed');
    }

    public function sitemap(Request $request): RedirectResponse
    {
        Artisan::call('et:sitemap', ['--user' => $request->user()->id]);

        return back()->with('status', trim(Artisan::output()) ?: 'Sitemap rebuilt');
    }

    private function form(Page $page): View
    {
        $page->loadMissing('site');

        return view('backend.lando.pages.form', [
            'page' => $page,
            'sites' => Site::orderBy('name')->get(),
            'templates' => Template::orderBy('name')->get(),
            'markets' => Market::where('status', 'Active')->orderBy('name')->get(),
            'groups' => PlanGroup::orderBy('name')->get(),
            'components' => $page->exists ? $page->components()->with('block.category')->get()->groupBy('zone') : collect(),
            'blocks' => ContentBlock::with('category')->where(fn ($q) => $q->whereNull('site_id')->orWhere('site_id', $page->site_id))->orderBy('name')->get(),
            'zones' => $page->file ? array_intersect_key(config('cms.zones'), array_flip(['top', 'bottom'])) : config('cms.zones'),
            'edits' => $page->exists
                ? HistoryItem::where('model', 'Page_model')->where('record_id', $page->id)->with('user')->latest('created_at')->latest('id')->limit(25)->get()
                : collect(),
        ]);
    }

    private function validated(Request $request, ?Page $page = null): array
    {
        $request->merge([
            'path' => trim((string) $request->input('path'), '/ ') ?: '/',
            'no_index' => $request->boolean('no_index'),
            'canonical' => $request->boolean('canonical'),
            'hard_cache' => $request->boolean('hard_cache'),
        ]);
        $siteId = $page?->site_id ?? $request->integer('site_id');
        // Content areas pick from the predefined content blocks for this site (or for every site)
        $blockRule = ['nullable', Rule::exists('content_blocks', 'id')->where(fn ($q) => $q->whereNull('site_id')->orWhere('site_id', $siteId))];

        $data = $request->validate([
            'site_id' => [$page ? 'prohibited' : 'required', 'exists:sites,id'],
            'title' => ['required', 'string', 'max:200'],
            'path' => ['required', 'string', 'max:200', 'regex:#^(/|[a-z0-9][a-z0-9/_-]*)$#',
                Rule::unique('pages')->where('site_id', $siteId)->ignore($page),
                function ($attr, $value, $fail) use ($page) {
                    if ($page?->file && $value !== $page->path) {
                        $fail('A built-in page keeps its path.');
                    } elseif (in_array(Str::before($value, '/'), config('cms.reserved'), true)) {
                        $fail('That path is used by the system. Pick another.');
                    }
                }],
            'template_id' => ['nullable', 'exists:templates,id'],
            'redirect' => ['nullable', 'string', 'max:300', 'regex:#^(/|https?://)#'],
            'status' => ['required', Rule::in(['Published', 'Draft'])],
            'html_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'meta_keywords' => ['nullable', 'string', 'max:300'],
            'head_content' => ['nullable', 'string', 'max:20000'],
            'promo_code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]*$/'],
            'phone' => ['nullable', 'string', 'max:20'],
            'market_id' => ['nullable', 'exists:markets,id'],
            'no_index' => ['boolean'],
            'canonical' => ['boolean'],
            'page_title' => ['nullable', 'string', 'max:200'],
            'content_primary_id' => $blockRule,
            'content_secondary_id' => $blockRule,
            'content_parent_id' => $blockRule,
            'content_auxiliary_id' => $blockRule,
            'content_amp_id' => $blockRule,
            'address_box_title' => ['nullable', 'string', 'max:120'],
            'market_label' => ['nullable', 'string', 'max:60'],
            'rep_id' => ['nullable', 'integer', Rule::in(array_keys(config('cms.reps')))],
            'hard_cache' => ['boolean'],
            'pricegrid_type' => ['nullable', Rule::in(array_keys(config('cms.price_grids')))],
            'pricegrid_header' => ['nullable', 'string', 'max:150'],
            'pricegrid_group_id' => ['nullable', 'required_with:pricegrid_type', 'exists:plan_groups,id'],
            'rating_formula' => ['nullable', Rule::in(array_keys(config('cms.rating_formulas')))],
            'copy_from_id' => ['nullable', 'integer', Rule::exists('pages', 'id')->whereNull('file'), Rule::notIn(array_filter([$page?->id]))],
            'copy_mode' => ['required_with:copy_from_id', 'nullable', Rule::in(['once', 'sync'])],
        ], [
            'copy_from_id.not_in' => 'A page can\'t copy itself.',
            'copy_from_id.exists' => 'Enter the ID of a page built in Lando (not a built-in file page).',
            'pricegrid_group_id.required_with' => 'Pick the plan group the price grid lists.',
            'path.regex' => 'Use lowercase letters, numbers, dashes and slashes in the URL path.',
            'redirect.regex' => 'Start the redirect with / or http(s)://.',
        ]);
        unset($data['site_id']);

        // Copy From Page ID: "copy contents one time" copies now; "keep contents in sync" shows that page's content
        $mode = $data['copy_mode'] ?? null;
        unset($data['copy_mode']);
        if (! empty($data['copy_from_id'])) {
            $source = Page::findOrFail($data['copy_from_id'])->contentSource();
            abort_if($source->id === $page?->id, 422, 'Those pages would copy each other.');
            if ($mode === 'once') {
                $data = array_merge($data, $source->only(Page::CONTENT_FIELDS));
                $data['copy_from_id'] = null;
            } else {
                $data['copy_from_id'] = $source->id;
            }
        } else {
            $data['copy_from_id'] = null;
        }

        return $page ? $data : $data + ['site_id' => $siteId];
    }
}
