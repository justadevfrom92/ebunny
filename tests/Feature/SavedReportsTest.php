<?php

namespace Tests\Feature;

use App\Models\ReportRun;
use App\Models\SavedReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Walker → Saved Reports: save a report's settings (or a past run's), see it listed, run it again, remove it. */
class SavedReportsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_save_run_and_remove_a_saved_report(): void
    {
        Storage::fake('local');
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin);
        $this->get(route('walker.saved'))->assertOk()->assertSee('On-flow orders this year')->assertSee('Payments last 30 days');

        // Save from the report form
        $this->post(route('walker.saved.store'), ['name' => 'Spring payments', 'report' => 'payments', 'start' => '2026-03-01', 'end' => '2026-05-31', 'status' => 'Success', 'format' => 'csv'])->assertRedirect();
        $s = SavedReport::where('name', 'Spring payments')->firstOrFail();
        $this->assertSame(['start' => '2026-03-01', 'end' => '2026-05-31', 'status' => 'Success'], $s->params);
        $this->get(route('walker.saved.show', $s))->assertOk()->assertSee('Run Now')->assertSee('2026-03-01');
        $this->get(route('walker.reports.show', ['payments', 'saved' => $s->id]))->assertOk()->assertSee('value="2026-03-01"', false);

        // Run it: a run tied to the saved report, with its settings
        $this->post(route('walker.saved.run', $s))->assertRedirect();
        $run = ReportRun::latest('id')->firstOrFail();
        $this->assertSame([$s->id, 'payments', '2026-03-01', 'csv'], [$run->saved_report_id, $run->report, $run->params['start'], $run->format]);
        $this->assertNotNull($s->fresh()->last_run_at);

        // Save from a past run
        $this->post(route('walker.saved.store'), ['name' => 'Same as run', 'report' => 'payments', 'run' => $run->id])->assertRedirect();
        $this->assertSame('2026-03-01', SavedReport::where('name', 'Same as run')->firstOrFail()->params['start']);

        // Not shared: someone else can't open it; shared ones they can
        $finance = User::where('email', 'finance@example.com')->firstOrFail();
        $this->actingAs($finance)->get(route('walker.saved.show', $s))->assertNotFound();
        $this->get(route('walker.saved'))->assertSee('On-flow orders this year')->assertDontSee('Spring payments');

        $this->actingAs($admin)->delete(route('walker.saved.destroy', $s))->assertRedirect(route('walker.saved'));
        $this->assertModelMissing($s);
        $this->assertNull($run->fresh()->saved_report_id);   // the run is kept
    }
}
