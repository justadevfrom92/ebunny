<?php

namespace Tests\Feature;

use App\Models\AiAgent;
use App\Models\AiModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sheriff → Cron Automations: list, a page per automation, add, edit, pause. Deputy keeps only customer-facing agents. */
class CronAutomationsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_automations_live_in_sheriff(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
        $a = AiAgent::automations()->where('name', 'Enrollment Fixer')->firstOrFail();
        $this->get(route('sheriff.automations'))->assertOk()->assertSee('Cron Automations')->assertSee('Enrollment Fixer')->assertSee('Every 15 minutes')
            ->assertSee(route('sheriff.automations.show', $a), false)->assertDontSee('/admin/deputy', false);
        $this->get(route('sheriff.automations.show', $a))->assertOk()->assertSee('Runs')->assertSee('Next Run')->assertSee('Pause');
        $this->get(route('sheriff.users.index'))->assertSee(route('sheriff.automations'), false);

        // Customer-facing agents aren't automations; automation agents aren't edited in Deputy
        $chat = AiAgent::talking()->firstOrFail();
        $this->get(route('sheriff.automations.show', $chat))->assertNotFound();
        $this->get(route('deputy.agents.edit', $a))->assertNotFound();
        $this->get(route('deputy.agents.create'))->assertOk()->assertDontSee('Fixes stuck enrollments')->assertDontSee('name="schedule"', false);

        $model = AiModel::firstOrFail();
        $base = ['name' => 'Payment Matcher 2', 'activity' => 'payments', 'schedule' => '0 * * * *', 'ai_model_id' => $model->id, 'temperature' => 0.2, 'max_tokens' => 2000, 'system_prompt' => 'Match payments.', 'active' => 1];
        $this->post(route('sheriff.automations.store'), ['schedule' => 'whenever'] + $base)->assertSessionHasErrors('schedule');
        $this->post(route('sheriff.automations.store'), ['activity' => 'chat'] + $base)->assertSessionHasErrors('activity');
        $this->post(route('sheriff.automations.store'), $base)->assertRedirect();
        $new = AiAgent::where('name', 'Payment Matcher 2')->firstOrFail();
        $this->get(route('sheriff.automations.edit', $new))->assertOk()->assertSee('Save Automation');
        $this->put(route('sheriff.automations.update', $new), ['schedule' => '30 6 * * *'] + $base)->assertRedirect(route('sheriff.automations.show', $new));
        $this->assertSame('30 6 * * *', $new->fresh()->schedule);
        $this->post(route('sheriff.automations.toggle', $new))->assertRedirect();
        $this->assertFalse($new->fresh()->active);
    }

    public function test_access_reason_is_cleared_without_exceptions(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin);
        $csr = User::where('email', 'csr@example.com')->firstOrFail();
        $this->get(route('sheriff.users.edit', $csr))->assertOk()->assertSee('Reason for the exceptions')->assertDontSee('Why it differs');
        $this->patch(route('sheriff.users.update', $csr), ['name' => $csr->name, 'email' => $csr->email, 'role_id' => $csr->role_id, 'access' => ['delete' => 'role'], 'access_note' => 'Old note'])->assertRedirect();
        $this->assertNull($csr->fresh()->access_note);
    }
}
