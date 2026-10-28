<?php

namespace Tests\Feature;

use App\Models\ByopProduct;
use App\Models\ContentBlock;
use App\Models\Market;
use App\Models\Page;
use App\Models\PlanGroup;
use App\Models\ReportRun;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The last pieces from the original: page editor fields, XLS/Backend report outputs, Astro's two Products pages. */
class OriginalFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    private function block(string $slug, string $html): int
    {
        return ContentBlock::create(['name' => $slug, 'slug' => $slug, 'html' => $html])->id;
    }

    private function page(array $fields): Page
    {
        $this->post('/admin/lando/pages', $fields + ['site_id' => Site::first()->id, 'status' => 'Published'])->assertSessionHasNoErrors()->assertRedirect();

        return Page::where('path', $fields['path'])->firstOrFail();
    }

    public function test_price_grid_market_label_rep_and_address_box_title(): void
    {
        $this->actingAs($this->admin());
        $group = PlanGroup::where('slug', 'featured')->firstOrFail();
        $this->page(['title' => 'Houston Plans', 'path' => 'houston-plans', 'market_label' => 'Houston', 'rep_id' => 1, 'address_box_title' => 'Check your Houston address',
            'content_primary_id' => $this->block('zip-box', '[[zip_form]]'), 'pricegrid_type' => 'table', 'pricegrid_group_id' => $group->id, 'rating_formula' => 'avg_2000',
            'market_id' => Market::where('name', 'TX-E-CENTERPOINT')->value('id')]);

        $html = $this->get('/houston-plans')->assertOk()->getContent();
        $this->assertStringContainsString('Plans for Houston', $html);
        $this->assertStringContainsString('class="price-table"', $html);
        $this->assertStringContainsString('Average price at 2,000 kWh', $html);
        $this->assertStringContainsString('Check your Houston address', $html);
        $this->assertStringContainsString('data-rep="1"', $html);
        $this->assertStringContainsString(e($group->plans()->first()->name), $html);

        $this->post('/admin/lando/pages', ['site_id' => Site::first()->id, 'title' => 'X', 'path' => 'x-grid', 'status' => 'Draft', 'pricegrid_type' => 'cards'])
            ->assertSessionHasErrors('pricegrid_group_id');
    }

    public function test_copy_from_page_once_or_in_sync_and_amp_and_hard_cache(): void
    {
        $this->actingAs($this->admin());
        [$original, $updated, $amp] = [$this->block('orig', '<p>Original words</p>'), $this->block('upd', '<p>Updated words</p>'), $this->block('amp', '<p>AMP words</p>')];
        $source = $this->page(['title' => 'Source', 'path' => 'source-page', 'content_primary_id' => $original, 'content_amp_id' => $amp]);

        $once = $this->page(['title' => 'Once', 'path' => 'once-page', 'copy_from_id' => $source->id, 'copy_mode' => 'once']);
        $this->assertNull($once->copy_from_id);
        $this->assertSame($original, $once->content_primary_id);

        $sync = $this->page(['title' => 'Synced', 'path' => 'synced-page', 'copy_from_id' => $source->id, 'copy_mode' => 'sync', 'hard_cache' => '1']);
        $this->assertSame($source->id, $sync->copy_from_id);
        $this->put('/admin/lando/pages/'.$source->id, ['title' => 'Source', 'path' => 'source-page', 'status' => 'Published', 'content_primary_id' => $updated, 'content_amp_id' => $amp]);

        $this->get('/synced-page')->assertOk()->assertSee('Updated words')->assertHeader('X-Page-Cache', 'hard');
        $this->get('/once-page')->assertSee('Original words')->assertSee('rel="amphtml"', false);
        $this->get('/amp/source-page')->assertOk()->assertSee('<html ⚡', false)->assertSee('AMP words', false);
        $this->get('/amp/about-us')->assertNotFound(); // no AMP content

        $this->put('/admin/lando/pages/'.$sync->id, ['title' => 'Synced', 'path' => 'synced-page', 'status' => 'Published', 'copy_from_id' => $sync->id, 'copy_mode' => 'sync'])
            ->assertSessionHasErrors('copy_from_id');
    }

    public function test_reports_download_xls_and_run_in_the_backend(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());

        foreach (['orders', 'notes', 'phonecalls'] as $report) {
            foreach ([0, 1] as $summary) {
                $this->get("/admin/walker/activity/$report?run=1&start=2020-01-01&format=xlsx&summary=$summary")->assertRedirect();
                $run = ReportRun::where('report', $report)->latest('id')->firstOrFail();
                $this->assertSame((bool) $summary, $run->summary);
                $this->assertStringContainsString('<sheetData>', $this->unzip(Storage::disk('local')->get($run->file))['xl/worksheets/sheet1.xml'] ?? '');
            }
        }

        $this->from('/admin/walker/activity/orders')->get('/admin/walker/activity/orders?run=1&start=2020-01-01')->assertRedirect();
        $run = ReportRun::where('report', 'orders')->latest('id')->firstOrFail();
        $this->assertSame('done', $run->status);
        $this->assertGreaterThan(0, $run->rows);
        Storage::disk('local')->assertExists($run->file);
        $this->get('/admin/walker/activity/orders')->assertSee(route('walker.activity.download', $run));
        $this->get(route('walker.activity.download', $run))->assertOk();

        // Only the person who ran it can download it
        $this->actingAs(User::where('email', 'csr@example.com')->firstOrFail())->get(route('walker.activity.download', $run))->assertRedirect(); // another user's run (and no Walker access)
    }

    public function test_astro_has_separate_modifier_and_byop_product_pages(): void
    {
        $this->actingAs($this->admin());
        $p = ByopProduct::firstOrFail();
        $this->get('/admin/astro/modifiers/products')->assertOk()->assertSee('Min Discount');
        $this->get('/admin/astro/products')->assertOk()->assertDontSee('Min Discount')->assertSee('Show on Website');

        $this->put('/admin/astro/modifiers/products', ['p' => [$p->id => ['min_discount' => '0', 'max_discount' => '-0.75']]])->assertRedirect();
        $this->assertEquals(-0.75, (float) $p->fresh()->max_discount);
        $this->put('/admin/astro/modifiers/products', ['p' => [$p->id => ['min_discount' => '-1', 'max_discount' => '0']]])->assertSessionHasErrors('p');
    }

    /** name => contents of a zip (read by hand, so the test needs no PHP zip extension either). */
    private function unzip(string $zip): array
    {
        $files = [];
        for ($at = 0; substr($zip, $at, 4) === "PK\x03\x04";) {
            $h = unpack('vver/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnlen/vxlen', substr($zip, $at + 4, 26));
            $name = substr($zip, $at + 30, $h['nlen']);
            $data = substr($zip, $at + 30 + $h['nlen'] + $h['xlen'], $h['csize']);
            $files[$name] = $h['method'] === 8 ? gzinflate($data) : $data;
            $at += 30 + $h['nlen'] + $h['xlen'] + $h['csize'];
        }

        return $files;
    }
}
