<?php

namespace Tests\Feature;

use App\Models\BlockCategory;
use App\Models\ByopProduct;
use App\Models\ContentBlock;
use App\Models\Market;
use App\Models\Page;
use App\Models\Plan;
use App\Models\PlanGroup;
use App\Models\Site;
use App\Models\Template;
use App\Models\User;
use App\Support\AdminMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Lando, Astro and Sheriff pages added to match the original menus. */
class CmsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    public function test_every_menu_link_in_every_app_opens(): void
    {
        $this->actingAs($this->admin());
        foreach (array_keys(config('admin.apps')) as $app) {
            // Build the menu as it would appear inside that app
            $this->get(route(config("admin.apps.$app.home")))->assertOk();
            foreach (AdminMenu::sections($app) as $heading => $items) {
                foreach ($items as $it) {
                    $this->get($it['url'])->assertOk();
                }
            }
        }
        $this->get('/admin/astro/rates/edit?plan='.Plan::first()->id)->assertOk()->assertSee('Update Rates: ');
        $this->get('/admin/astro/rates/edit?all=1')->assertOk()->assertSee('Hide inactive plans');
        $this->get('/admin/sheriff/integrations/nope')->assertNotFound();
    }

    public function test_no_admin_app_links_into_another_app(): void
    {
        $this->actingAs($this->admin());
        $apps = array_keys(config('admin.apps'));
        foreach ($apps as $app) {
            $urls = collect(AdminMenu::sections($app))->flatten(1)->pluck('url');
            foreach ($urls as $url) {
                $this->assertStringStartsWith(url('/admin/'.$app), $url, "$app menu has $url");
                $html = $this->get($url)->assertOk()->getContent();
                // Account numbers link to the Corral account everywhere; that's the one allowed link out
                $html = preg_replace('#href="[^"]*/admin/corral/customers/\d+(/ticket|/logs/\w+)?"#', '', $html);
                foreach (array_diff($apps, [$app]) as $other) {
                    $this->assertStringNotContainsString(url('/admin/'.$other.'/'), $html, "$url links into $other");
                }
            }
        }
        // Astro's website screens, deeper pages included
        // Pages are Lando's only
        $page = Page::where('path', 'about-us')->firstOrFail();
        $this->get('/admin/astro/pages/'.$page->id.'/edit')->assertNotFound();
        $this->get('/admin/astro/plans/'.Plan::first()->id.'/edit')->assertOk();
        $this->get('/admin/astro/groups/'.PlanGroup::first()->id.'/edit')->assertOk();
    }

    public function test_lando_pages_blocks_and_components_show_on_the_website(): void
    {
        $this->actingAs($this->admin());
        $site = Site::firstOrFail();

        // A widget in a category, used both by code and as a component
        $this->post('/admin/lando/block-categories', ['name' => 'Promotions'])->assertRedirect();
        $cat = BlockCategory::where('name', 'Promotions')->firstOrFail();
        $this->post('/admin/lando/blocks', ['name' => 'Fall Promo', 'slug' => 'fall-promo', 'category_id' => $cat->id, 'html' => '<p class="promo">Fall promo: save 10%</p>[[block|rewards-promo-band]] [[unknown|x=1]]'])->assertRedirect();
        $block = ContentBlock::where('slug', 'fall-promo')->firstOrFail();

        $this->post('/admin/lando/pages', ['site_id' => $site->id, 'title' => 'Fall Savings', 'path' => 'fall-savings', 'status' => 'Draft',
            'content_primary_id' => $block->id, 'page_title' => 'Save This Fall'])->assertRedirect();
        $page = Page::where('path', 'fall-savings')->firstOrFail();
        $this->post('/admin/lando/pages/'.$page->id.'/components', ['content_block_id' => ContentBlock::where('slug', 'rewards-promo-band')->value('id'), 'zone' => 'sidebar'])->assertRedirect();

        // Draft: admins can view it, the public can't
        $this->get('/fall-savings')->assertOk()->assertSee('Save This Fall')->assertSee('Fall promo: save 10%', false)->assertSee('rewards-band', false)->assertSee('Draft');
        auth()->logout();
        $this->get('/fall-savings')->assertNotFound();

        $this->actingAs($this->admin())->put('/admin/lando/pages/'.$page->id, ['title' => 'Fall Savings', 'path' => 'fall-savings', 'status' => 'Published',
            'content_primary_id' => $block->id, 'canonical' => '1'])->assertRedirect();
        auth()->logout();
        $this->get('/fall-savings')->assertOk()->assertSee('Fall promo: save 10%', false)->assertDontSee('[[unknown', false)->assertDontSee('Draft —');

        // Built-in pages get their components through /site/components
        // (the plans page has none now; the rewards band sits in the About Us sidebar)
        $this->getJson('/site/components?page=plans.html')->assertOk()->assertJsonPath('top', fn ($html) => ! str_contains((string) $html, 'rewards-band'));
        $this->get('/about-us')->assertOk()->assertSee('rewards-band', false);
        // Redirects and reserved paths
        $this->get('/epicenter')->assertRedirect('/plans?msid=130001');
        $this->get('/admin/lando/nope/nope')->assertNotFound(); // never looked up as a website page
    }

    public function test_sites_answer_on_their_own_domain(): void
    {
        $this->actingAs($this->admin());
        $biz = Site::create(['name' => 'Business Site', 'domain' => 'business.example.test', 'status' => 'Active']);
        $this->post('/admin/lando/blocks', ['name' => 'Biz Solar', 'slug' => 'biz-solar', 'site_id' => $biz->id, 'html' => '<p>Business solar.</p>']);
        $solar = ContentBlock::where('slug', 'biz-solar')->firstOrFail();
        $this->post('/admin/lando/pages', ['site_id' => $biz->id, 'title' => 'Solar', 'path' => 'solar', 'status' => 'Published', 'content_primary_id' => $solar->id])->assertRedirect();
        // A block that belongs to another site isn't offered (or accepted) on the main site
        $this->post('/admin/lando/pages', ['site_id' => Site::first()->id, 'title' => 'Solar', 'path' => 'solar-x', 'status' => 'Published', 'content_primary_id' => $solar->id])->assertSessionHasErrors('content_primary_id');
        // Content is chosen from the list, not typed in
        $this->get('/admin/lando/pages/create')->assertOk()->assertSee('<select id="content_primary_id"', false)->assertDontSee('<textarea id="content_primary"', false);
        auth()->logout();

        $this->get('http://business.example.test/solar')->assertOk()->assertSee('Business solar.');
        $this->get('http://localhost/solar')->assertNotFound(); // not a page on the main site
    }

    public function test_templates_markets_groups_and_page_settings(): void
    {
        $this->actingAs($this->admin());

        $this->post('/admin/lando/templates', ['name' => 'spring-campaign', 'description' => 'Campaign landing page'])->assertRedirect('/admin/lando/templates');
        $t = Template::where('name', 'spring-campaign')->firstOrFail();
        $this->get('/admin/lando/templates/'.$t->id.'/edit')->assertOk();
        $this->put('/admin/lando/templates/'.$t->id, ['name' => 'spring-campaign', 'description' => 'x', 'file' => '../../.env'])->assertSessionHasErrors('file');

        $this->post('/admin/sheriff/markets', ['name' => 'TX-E-SHARYLAND', 'short' => 'Sharyland', 'description' => 'Sharyland Utilities', 'region' => 'South',
            'type' => 'TDSP', 'state' => 'TX', 'commodity' => 'Electric', 'units' => 'kWh', 'status' => 'Active'])->assertRedirect();
        $m = Market::where('name', 'TX-E-SHARYLAND')->firstOrFail();
        $this->get('/admin/sheriff/markets/'.$m->id.'/edit')->assertOk()->assertSee('Sharyland');
        $this->post('/admin/sheriff/markets', ['name' => 'bad name'])->assertSessionHasErrors(['name', 'short']);

        [$a, $b] = Plan::where('active', true)->take(2)->pluck('id')->all();
        $this->post('/admin/astro/groups', ['name' => 'Spring Promo', 'slug' => 'spring-promo', 'plans' => [$b, $a]])->assertRedirect('/admin/astro/groups');
        $g = PlanGroup::where('slug', 'spring-promo')->firstOrFail();
        $this->assertSame([$b, $a], $g->plans()->orderBy('plan_group_plan.position')->pluck('plans.id')->all());
        $this->put('/admin/astro/groups/'.$g->id, ['name' => 'Spring', 'slug' => 'spring-promo', 'plans' => [$a]])->assertRedirect();
        $this->assertSame([$a], $g->plans()->pluck('plans.id')->all());

        $page = Page::where('status', 'Published')->where('path', '!=', '/')->firstOrFail();
        $this->put('/admin/lando/pages/'.$page->id, ['title' => $page->title, 'path' => $page->path, 'status' => 'Published', 'no_index' => '1', 'html_title' => 'SEO Title'])->assertRedirect();
        $this->assertTrue($page->fresh()->no_index);
        $this->get('/admin/lando/pages/'.$page->id.'/edit')->assertOk()->assertSee('SEO Title')->assertSee('Edits');
    }

    public function test_byop_discounts_upload_is_all_or_nothing(): void
    {
        $this->actingAs($this->admin());
        $p = ByopProduct::firstOrFail();
        $before = $p->rate_adj;

        $bad = UploadedFile::fake()->createWithContent('d.csv', "key,rate_adj,min_discount,max_discount\n{$p->key},-0.25,0,-0.5\nnot-a-product,1\n");
        $this->post('/admin/astro/uploads/byop', ['file' => $bad])->assertSessionHasErrors('file');
        $this->assertEquals($before, $p->fresh()->rate_adj);

        $good = UploadedFile::fake()->createWithContent('d.csv', "key,rate_adj,min_discount,max_discount\n{$p->key},-0.25,0,-0.5\n");
        $this->post('/admin/astro/uploads/byop', ['file' => $good])->assertRedirect('/admin/astro/modifiers/products');
        $p->refresh();
        $this->assertEquals([-0.25, 0.0, -0.5], [(float) $p->rate_adj, (float) $p->min_discount, (float) $p->max_discount]);
    }

    public function test_sheriff_sitemap_and_tdsp_fees(): void
    {
        $this->actingAs($this->admin());
        $this->post('/admin/sheriff/sitemap')->assertRedirect();
        $this->get('/admin/sheriff/sitemap')->assertOk()->assertSee('sitemap.xml');
        $this->get('/admin/astro/fees')->assertOk()->assertSee(route('astro.fees.update'));
        $this->get('/admin/sheriff/fees')->assertNotFound();
        $this->get('/admin/sheriff/integrations/stripe')->assertOk()->assertSee('STRIPE_SECRET')->assertDontSee((string) config('admin.integrations.stripe.env.STRIPE_SECRET') ?: 'unset-value-guard');
    }

    public function test_rows_with_one_action_link_the_entry_instead_of_a_button(): void
    {
        $this->actingAs($this->admin());
        $t = Template::firstOrFail();
        $this->get('/admin/lando/templates')->assertOk()->assertSee('href="'.route('lando.templates.edit', $t).'"><b>', false)->assertDontSee('>Edit</a>', false);
        $m = Market::firstOrFail();
        $this->get('/admin/sheriff/markets')->assertOk()->assertSee('href="'.route('sheriff.markets.edit', $m).'"><b>', false)->assertDontSee('>Edit</a>', false);
        $this->get('/admin/corral/queues')->assertOk()->assertDontSee('>Open</a>', false);
        $this->get('/admin/caboose/channels')->assertOk()->assertDontSee('>Edit</a>', false);
        $this->get('/admin/sheriff/cached-tables')->assertOk()->assertDontSee('>View</a>', false);
    }
}
