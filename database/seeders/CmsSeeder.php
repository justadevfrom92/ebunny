<?php

namespace Database\Seeders;

use App\Models\BlockCategory;
use App\Models\ContentBlock;
use App\Models\Page;
use App\Models\PageComponent;
use App\Models\Site;
use App\Models\Template;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** Lando: the website, its templates and pages, content blocks and page components. */
class CmsSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::create(['name' => config('brand.name'), 'domain' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost']);

        foreach (DatabaseSeeder::data('templates') as $t) {
            Template::create(['name' => $t['name'], 'description' => $t['desc']]);
        }
        $templates = Template::pluck('id', 'name');
        $files = config('cms.files');

        // Categories come from the block name prefix ("Home - Hero" → Home)
        foreach (DatabaseSeeder::data('blocks') as $b) {
            $category = BlockCategory::firstOrCreate(['name' => Str::before($b['name'], ' - ')]);
            $block = ContentBlock::create(['name' => $b['name'], 'slug' => Str::slug($b['name']), 'category_id' => $category->id, 'html' => $b['html']]);
            $block->forceFill(['id' => $b['id'], 'updated_at' => $b['updated'], 'created_at' => $b['updated']])->save();
        }

        $pageContent = BlockCategory::create(['name' => 'Page Content']);
        foreach (DatabaseSeeder::data('pages') as $p) {
            $content = isset($p['content'])
                ? ContentBlock::create(['name' => $p['title'].' - Primary', 'slug' => 'page-'.Str::slug($p['path']).'-primary', 'html' => $p['content'], 'category_id' => $pageContent->id, 'site_id' => $site->id])
                : null;
            Page::create([
                'content_primary_id' => $content?->id,
                'site_id' => $site->id, 'path' => $p['path'], 'title' => $p['title'], 'template_id' => $templates[$p['template']] ?? null,
                'redirect' => isset($files[$p['path']]) ? null : ($p['redirect'] ?: null), 'status' => $p['status'], 'file' => $files[$p['path']] ?? null,
            ]);
        }

        // The rewards band as a component in the About Us sidebar
        $band = ContentBlock::where('slug', 'rewards-promo-band')->first();
        foreach (['about-us' => 'sidebar'] as $path => $zone) {
            $page = Page::where('path', $path)->first();
            if ($band && $page) {
                PageComponent::create(['page_id' => $page->id, 'content_block_id' => $band->id, 'zone' => $zone]);
            }
        }
    }
}
