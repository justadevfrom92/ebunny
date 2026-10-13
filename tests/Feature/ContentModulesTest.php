<?php

namespace Tests\Feature;

use App\Models\ContentBlock;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Content blocks as modules: {{variables}} filled from the block code or the page component; the compact blocks table. */
class ContentModulesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_modules_take_text_from_the_code_and_from_components(): void
    {
        $m = ContentBlock::where('slug', 'location-intro')->firstOrFail();
        $this->assertSame(['city' => 'your city', 'utility' => 'local utility', 'note' => 'Compare fixed-rate plans and start service in minutes.'], $m->variables());
        $this->assertStringContainsString('|city=your city|utility=local utility|', $m->shortcode());

        // Pages pass their own text through the code; values are text, not HTML
        $this->get('/locations/dallas')->assertOk()->assertSee('Electricity plans in Dallas')->assertSee('the Oncor service area', false);
        $this->get('/locations/houston')->assertSee('CenterPoint');
        ContentBlock::where('slug', 'page-locationsdallas-primary')->update(['html' => '[[block|location-intro|city=<b>Big D</b>]]']);
        $this->get('/locations/dallas')->assertSee('&lt;b&gt;Big D&lt;/b&gt;', false)->assertSee('local utility');

        // As a page component, the page editor fills in each variable
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $page = Page::where('path', 'about-us')->firstOrFail();
        $band = ContentBlock::where('slug', 'promo-band')->firstOrFail();
        $this->actingAs($admin)->post(route('lando.pages.components.store', $page), ['content_block_id' => $band->id, 'zone' => 'sidebar'])->assertRedirect();
        $c = $page->components()->where('content_block_id', $band->id)->firstOrFail();
        $this->get(route('lando.pages.edit', $page))->assertSee('name="vars[heading]"', false)->assertSee('Save Text');
        $this->patch(route('lando.pages.components.update', $c), ['vars' => ['heading' => 'Fall Savings', 'button' => 'Shop now', 'bogus' => 'x']])->assertRedirect();
        $this->assertSame(['heading' => 'Fall Savings', 'button' => 'Shop now'], $c->fresh()->vars);
        auth()->logout();
        $this->get('/about-us')->assertSee('Fall Savings')->assertSee('Shop now')->assertSee('Earn stars on every on-time payment.');
    }

    public function test_blocks_table_is_compact_and_entries_are_links(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
        $b = ContentBlock::where('slug', 'page-get-to-learninhow-to-read-a-texas-electricity-facts-label-primary')->firstOrFail();
        $html = $this->get(route('lando.blocks.index'))->assertOk()->assertSee('Module · 3 variables')->assertDontSee('>Edit</a>', false)->getContent();
        $this->assertStringContainsString('href="'.route('lando.blocks.edit', $b).'" title="'.e($b->name).'"', $html);
        $this->assertStringNotContainsString('[[block|', $html);   // codes are on each block's page, not in the table
        $this->get(route('lando.blocks.edit', ContentBlock::where('slug', 'promo-band')->firstOrFail()))->assertOk()->assertSee('Make it a module')->assertSee('id="b-vars"', false);
    }
}
