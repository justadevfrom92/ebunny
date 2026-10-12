<?php

namespace App\Http\Controllers\Backend\Lando;

use App\Http\Controllers\Controller;
use App\Models\BlockCategory;
use App\Models\ContentBlock;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Content blocks: reusable HTML widgets. Put one on a page as a component
 * (Edit Page → Page Components) or inside page content with [[block|slug]].
 */
class BlockController extends Controller
{
    public function index(Request $request): View
    {
        $category = $request->query('category');

        return view('backend.lando.blocks.index', [
            'categories' => BlockCategory::withCount('blocks')->orderBy('name')->get(),
            'uncategorized' => ContentBlock::whereNull('category_id')->count(),
            'category' => $category,
            'blocks' => ContentBlock::with(['editor', 'site', 'category'])->withCount('components')
                ->when($category === '0', fn ($q) => $q->whereNull('category_id'))
                ->when($category && $category !== '0', fn ($q) => $q->where('category_id', (int) $category))
                ->orderBy('name')->get(),
        ]);
    }

    /** New Category: its own page with a simple form. */
    public function categoryForm(): View
    {
        return view('backend.lando.blocks.category-form');
    }

    public function addCategory(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60', 'unique:block_categories,name']]);
        $category = BlockCategory::create($data);

        return redirect()->route('lando.blocks.index', ['category' => $category->id])->with('status', 'Category added');
    }

    public function create(Request $request): View
    {
        return $this->form(new ContentBlock(['html' => '', 'category_id' => $request->integer('category') ?: null]));
    }

    public function store(Request $request): RedirectResponse
    {
        $block = ContentBlock::create($this->validated($request) + ['updated_by' => $request->user()->id]);

        return redirect()->route('lando.blocks.edit', $block)->with('status', 'Block created');
    }

    public function edit(ContentBlock $block): View
    {
        return $this->form($block);
    }

    public function update(Request $request, ContentBlock $block): RedirectResponse
    {
        $block->update($this->validated($request, $block) + ['updated_by' => $request->user()->id]);

        return redirect()->route('lando.blocks.edit', $block)->with('status', 'Block saved');
    }

    /** Needs the "delete" right. Also takes the block off every page it was on. */
    public function destroy(ContentBlock $block): RedirectResponse
    {
        Gate::authorize('delete');
        $block->delete();

        return redirect()->route('lando.blocks.index')->with('status', 'Block deleted');
    }

    private function form(ContentBlock $block): View
    {
        return view('backend.lando.blocks.form', [
            'block' => $block,
            'categories' => BlockCategory::orderBy('name')->get(),
            'sites' => Site::orderBy('name')->get(),
            'usedOn' => $block->exists ? $block->components()->with('page.site')->get() : collect(),
        ]);
    }

    private function validated(Request $request, ?ContentBlock $block = null): array
    {
        $request->merge(['slug' => strtolower(trim((string) $request->input('slug')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9_-]*$/', Rule::unique('content_blocks')->ignore($block)],
            'category_id' => ['nullable', 'exists:block_categories,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'html' => ['nullable', 'string', 'max:200000'],
        ], ['slug.regex' => 'Use lowercase letters, numbers, dashes and underscores. Pages use it in [[block|slug]].']) + ['html' => ''];
    }
}
