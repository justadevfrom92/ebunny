<?php

namespace Tests\Feature;

use App\Models\ReportRun;
use App\Models\ReportUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Walker: report status home page, run pages with Rerun, the report library and uploads. */
class WalkerTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    public function test_home_lists_runs_by_status(): void
    {
        $this->actingAs($this->admin());
        $html = $this->get('/admin/walker')->assertOk()->assertDontSee('class="tabs"', false)->getContent();
        // Running on top, then Errors, then Completed, each its own table
        $running = strpos($html, 'id="runs-running"');
        $problem = strpos($html, 'id="runs-problem"');
        $done = strpos($html, 'id="runs-done"');
        $this->assertTrue($running < $problem && $problem < $done);
        $this->assertStringContainsString('Emails &amp; Texts', substr($html, $running, $problem - $running));
        $this->assertStringContainsString('ERCOT Transactions', substr($html, $problem, $done - $problem));
        $this->assertStringContainsString('Did not finish', substr($html, $problem, $done - $problem));
        $this->assertStringContainsString('Accounts Receivable Aging', substr($html, $done));
        // The live update asks for just the tables
        $this->get('/admin/walker?part=tables')->assertOk()->assertSee('id="runs-done"', false)->assertDontSee('<html', false);
        $this->get('/admin/walker')->assertOk()->assertSee('Report Status');
    }

    public function test_run_page_shows_the_model_and_rerun_creates_a_new_run(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());
        $run = ReportRun::where('report', 'payments')->firstOrFail();
        $this->get('/admin/walker/runs/'.$run->id)->assertOk()->assertSee('ItemPayment_model')->assertSee('Rerun')->assertSee('Model Reference')->assertSee('Paid On');

        $this->post('/admin/walker/runs/'.$run->id.'/rerun')->assertRedirect();
        $new = ReportRun::latest('id')->firstOrFail();
        $this->assertSame([$run->id, 'payments', 'done'], [$new->rerun_of, $new->report, $new->status]); // the queue runs jobs right away in tests
        $this->assertGreaterThan(0, $new->rows);
        $this->get('/admin/walker/runs/'.$new->id.'/download')->assertOk();

        $failed = ReportRun::where('status', 'failed')->firstOrFail();
        $this->get('/admin/walker/runs/'.$failed->id)->assertOk()->assertSee('timed out');
    }

    public function test_every_report_in_the_library_runs(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());
        $this->get('/admin/walker/reports')->assertOk();
        foreach (array_keys(config('walker.reports')) as $key) {
            $this->get('/admin/walker/reports/'.$key)->assertOk();
            $this->post('/admin/walker/reports/'.$key, ['start' => '2020-01-01'])->assertRedirect();
            $run = ReportRun::where('report', $key)->latest('id')->firstOrFail();
            $this->assertSame('done', $run->status, $key.': '.$run->error);
        }
        $this->post('/admin/walker/reports/orders', ['start' => '2026-02-01', 'end' => '2026-01-01'])->assertSessionHasErrors('end');
        $this->get('/admin/walker/reports/not-a-report')->assertNotFound();
    }

    public function test_corral_backend_reports_show_in_walker(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());
        $this->from('/admin/walker/activity/notes')->get('/admin/walker/activity/notes?run=1&start=2020-01-01')->assertRedirect();
        $run = ReportRun::where('app', 'walker')->where('report', 'notes')->latest('id')->firstOrFail();
        $this->assertSame('done', $run->status);
        $this->get('/admin/walker')->assertSee(route('walker.runs.show', $run));
    }

    public function test_uploads_and_access(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());
        $this->post('/admin/walker/uploads', ['title' => 'Vendor file', 'category' => 'Operations', 'file' => UploadedFile::fake()->createWithContent('v.csv', "a,b\n1,2\n")])->assertRedirect();
        $u = ReportUpload::where('title', 'Vendor file')->firstOrFail();
        $this->get('/admin/walker/uploads/'.$u->id)->assertOk();
        $this->post('/admin/walker/uploads', ['title' => 'Bad', 'category' => 'Operations', 'file' => UploadedFile::fake()->create('x.exe', 10)])->assertSessionHasErrors('file');
        $this->delete('/admin/walker/uploads/'.$u->id)->assertRedirect();
        $this->assertNull($u->fresh());

        // Customer Service doesn't have Walker
        $this->actingAs(User::where('email', 'csr@example.com')->firstOrFail())->get('/admin/walker')->assertRedirect();
    }

    public function test_report_pages_have_download_and_rerun_icons(): void
    {
        $this->actingAs($this->admin());
        $run = ReportRun::where('report', 'payments')->firstOrFail();
        // Top right of a run's page and of each report's page: save icon (download) and reload icon (rerun)
        $this->get('/admin/walker/runs/'.$run->id)->assertOk()->assertSee('report-actions ra-page', false)
            ->assertSee('aria-label="Download Excel (.xlsx)"', false)->assertSee('aria-label="Rerun report"', false)->assertSee(route('walker.runs.rerun', $run), false);
        $this->get('/admin/walker/reports/payments')->assertOk()->assertSee('Download latest Excel (.xlsx)')->assertSee(route('walker.runs.rerun', $run), false);
        $this->get('/admin/walker/reports/orders')->assertOk()->assertSee(route('walker.reports.run', 'orders'), false);   // never run: reload runs it
        // Every run row in Report Status has both icons (the tables are paged)
        $html = $this->get('/admin/walker')->getContent();
        $this->assertGreaterThan(10, substr_count($html, '<tr class="click" data-href="'.url('/admin/walker/runs')));
        $this->assertSame(substr_count($html, '<tr class="click" data-href="'.url('/admin/walker/runs')), substr_count($html, 'aria-label="Rerun report"'));
        // Corral reports: each of your runs has a download and a rerun icon
        $this->get('/admin/walker/activity/notes?run=1&start=2025-01-01')->assertRedirect();
        $mine = ReportRun::where('report', 'notes')->latest('id')->firstOrFail();
        $this->get('/admin/walker/activity/notes')->assertOk()->assertSee('Download Excel', false)->assertSee(route('admin.files.rerun', $mine), false);
    }
}
