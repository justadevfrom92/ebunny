<?php

namespace Tests\Feature;

use App\Models\ErmCase;
use App\Models\ErmChecklistItem;
use App\Models\ErmCourse;
use App\Models\ErmEnrollment;
use App\Models\ErmKudo;
use App\Models\ErmReview;
use App\Models\ErmTimeOff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bunkhouse (employee relationship management): pages, reviews, check-ins, training, kudos, time off, HR cases and checklists. */
class BunkhouseTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function hr(): User
    {
        return User::where('email', 'hr@example.com')->firstOrFail();
    }

    public function test_pages_and_access(): void
    {
        $this->actingAs($this->hr());
        $person = User::where('email', 'csr@example.com')->firstOrFail();
        $ext = User::whereNotNull('company')->where('active', true)->firstOrFail();
        foreach (['bunkhouse.dashboard', 'bunkhouse.people', 'bunkhouse.reviews', 'bunkhouse.checkins', 'bunkhouse.training', 'bunkhouse.kudos', 'bunkhouse.time-off', 'bunkhouse.cases', 'bunkhouse.lifecycle',
            'bunkhouse.reviews.create', 'bunkhouse.checkins.create', 'bunkhouse.time-off.create', 'bunkhouse.cases.create'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('/admin/sheriff', false);
        }
        $this->get(route('bunkhouse.people.show', $person))->assertOk()->assertSee('Reviews')->assertSee('Training')->assertSee('Kudos Received');
        $this->get(route('bunkhouse.people.show', $ext))->assertOk()->assertSee($ext->company)->assertSee('3rd party');
        $this->get(route('bunkhouse.reviews.show', ErmReview::firstOrFail()))->assertOk();
        $this->get(route('bunkhouse.training.show', ErmCourse::firstOrFail()))->assertOk()->assertSee('Assign');
        $this->get(route('bunkhouse.cases.show', ErmCase::firstOrFail()))->assertOk()->assertSee('Updates');
        // HR cases are only for Bunkhouse users
        $this->actingAs($person)->get(route('bunkhouse.cases'))->assertRedirect();
    }

    public function test_workflows(): void
    {
        $hr = $this->hr();
        $this->actingAs($hr);
        $csr = User::where('email', 'csr@example.com')->firstOrFail();

        // Schedule and complete a review
        $this->post(route('bunkhouse.reviews.store'), ['user_ids' => [$csr->id], 'kind' => 'Annual', 'period' => '2026 H2', 'due_on' => today()->addDays(20)->toDateString()])->assertRedirect();
        $r = ErmReview::where('user_id', $csr->id)->where('period', '2026 H2')->firstOrFail();
        $this->assertSame($csr->manager_id, $r->reviewer_id);
        $this->put(route('bunkhouse.reviews.update', $r), ['status' => 'complete'])->assertSessionHasErrors(['rating', 'strengths']);
        $this->put(route('bunkhouse.reviews.update', $r), ['status' => 'complete', 'rating' => 4, 'strengths' => 'Great with customers'])->assertRedirect();
        $this->assertNotNull($r->fresh()->completed_at);

        // Check-in, kudos (not to yourself), time off with default hours, approve
        $this->post(route('bunkhouse.checkins.store'), ['user_id' => $csr->id, 'held_on' => today()->toDateString(), 'mood' => 4, 'topics' => 'Summer volume'])->assertRedirect();
        $this->post(route('bunkhouse.kudos.store'), ['to_id' => $hr->id, 'value' => 'Teamwork', 'message' => 'Me!'])->assertSessionHasErrors('to_id');
        $this->post(route('bunkhouse.kudos.store'), ['to_id' => $csr->id, 'value' => 'Teamwork', 'message' => 'Thanks!'])->assertRedirect();
        $this->assertTrue(ErmKudo::where('to_id', $csr->id)->where('message', 'Thanks!')->exists());
        $monday = today()->next('Monday');
        $this->post(route('bunkhouse.time-off.store'), ['user_id' => $csr->id, 'type' => 'PTO', 'starts_on' => $monday->toDateString(), 'ends_on' => $monday->copy()->addDays(6)->toDateString()])->assertRedirect();
        $t = ErmTimeOff::where('user_id', $csr->id)->latest('id')->firstOrFail();
        $this->assertSame(40.0, $t->hours);   // 5 weekdays
        $this->post(route('bunkhouse.time-off.decide', $t), ['do' => 'approved'])->assertRedirect();
        $this->assertSame('approved', $t->fresh()->status);

        // Training: assign and complete
        $course = ErmCourse::firstOrFail();
        $this->post(route('bunkhouse.training.assign', $course), ['user_ids' => [$csr->id], 'due_on' => today()->addDays(10)->toDateString()])->assertRedirect();
        $e = ErmEnrollment::where('course_id', $course->id)->where('user_id', $csr->id)->whereDate('assigned_on', today())->firstOrFail();
        $this->post(route('bunkhouse.training.complete', $e))->assertRedirect();
        $this->assertNotNull($e->fresh()->completed_on);

        // HR case: open, then closing needs a resolution
        $this->post(route('bunkhouse.cases.store'), ['user_id' => $csr->id, 'type' => 'Concern', 'subject' => 'Shift swap', 'priority' => 'Low', 'body' => 'Asked about swapping shifts.', 'assigned_to' => $hr->id])->assertRedirect();
        $case = ErmCase::where('subject', 'Shift swap')->firstOrFail();
        $this->put(route('bunkhouse.cases.update', $case), ['status' => 'closed'])->assertSessionHasErrors('body');
        $this->put(route('bunkhouse.cases.update', $case), ['status' => 'closed', 'body' => 'Swap approved'])->assertRedirect();
        $this->assertSame('Swap approved', $case->fresh()->resolution);

        // Onboarding checklist
        $new = User::where('email', 'pricing@example.com')->firstOrFail();
        $this->post(route('bunkhouse.lifecycle.start'), ['user_id' => $new->id, 'kind' => 'onboarding', 'date' => today()->addWeek()->toDateString()])->assertRedirect(route('bunkhouse.people.show', $new));
        $item = ErmChecklistItem::where('user_id', $new->id)->firstOrFail();
        $this->assertSame(count(ErmChecklistItem::TEMPLATES['onboarding']), ErmChecklistItem::where('user_id', $new->id)->count());
        $this->post(route('bunkhouse.lifecycle.toggle', $item))->assertRedirect();
        $this->assertSame($hr->id, $item->fresh()->done_by);
        $this->post(route('bunkhouse.lifecycle.start'), ['user_id' => $new->id, 'kind' => 'onboarding', 'date' => today()->toDateString()])->assertSessionHasErrors('user_id');
    }
}
