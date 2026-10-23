<?php

namespace Tests\Feature;

use App\Models\CrmActivity;
use App\Models\CrmLead;
use App\Models\CrmPartner;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Corral → CRM: pipeline, leads, stages, activity log, follow-ups and partners. */
class CrmTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function csr(): User
    {
        return User::where('email', 'csr@example.com')->firstOrFail();
    }

    public function test_pages_open_with_sample_data(): void
    {
        $this->actingAs($this->csr());
        $lead = CrmLead::open()->firstOrFail();
        $partner = CrmPartner::firstOrFail();
        $this->get(route('corral.crm.pipeline'))->assertOk()->assertSee('Open Pipeline')->assertSee('Proposal Sent')->assertSee('Recent Wins');
        $this->get(route('corral.crm.leads', ['stage' => 'won']))->assertOk()->assertDontSee('No leads match.');
        $this->get(route('corral.crm.leads.show', $lead))->assertOk()->assertSee($lead->displayName())->assertSee('Timeline')->assertSee('Log Activity');
        $this->get(route('corral.crm.leads.create'))->assertOk()->assertSee('pick-list', false)->assertSee('<select name="kind"', false)->assertDontSee('<select id="owner', false);   // kind, source and stage are dropdowns; the owner is still a pick list
        $this->get(route('corral.crm.tasks'))->assertOk()->assertSee('Overdue')->assertSee('Due Today');
        $this->get(route('corral.crm.partners'))->assertOk()->assertSee($partner->name);
        $this->get(route('corral.crm.partners.show', $partner))->assertOk()->assertSee('Commission Earned');
        // Corral users see it; others don't
        $this->actingAs(User::where('email', 'pricing@example.com')->firstOrFail())->get(route('corral.crm.pipeline'))->assertRedirect();
    }

    public function test_lead_lifecycle(): void
    {
        $this->actingAs($this->csr());
        $this->post(route('corral.crm.leads.store'), ['name' => 'Pat Sample', 'kind' => 'Small Business', 'source' => 'Broker', 'annual_kwh' => 50000])
            ->assertSessionHasErrors(['company', 'partner_id']);
        $partner = CrmPartner::where('kind', 'Broker')->firstOrFail();
        $this->post(route('corral.crm.leads.store'), ['name' => 'Pat Sample', 'company' => 'Sample Feed Store', 'kind' => 'Small Business', 'source' => 'Broker', 'partner_id' => $partner->id, 'annual_kwh' => 50000])->assertRedirect();
        $lead = CrmLead::where('company', 'Sample Feed Store')->firstOrFail();
        $this->assertSame(7000.0, $lead->est_value);   // estimated at 14¢/kWh
        $this->assertSame('new', $lead->stage);

        // A call moves a new lead to Contacted; a follow-up sets the next follow-up date
        $this->post(route('corral.crm.leads.activity', $lead), ['type' => 'call', 'subject' => 'Intro call'])->assertRedirect();
        $this->assertSame('contacted', $lead->fresh()->stage);
        $this->post(route('corral.crm.leads.activity', $lead), ['type' => 'task', 'subject' => 'Send quote'])->assertSessionHasErrors('due_on');
        $this->post(route('corral.crm.leads.activity', $lead), ['type' => 'task', 'subject' => 'Send quote', 'due_on' => today()->addDays(3)->toDateString()])->assertRedirect();
        $this->assertSame(today()->addDays(3)->toDateString(), $lead->fresh()->next_follow_up_on->toDateString());
        $task = CrmActivity::where('lead_id', $lead->id)->where('type', 'task')->firstOrFail();
        $this->post(route('corral.crm.tasks.complete', $task))->assertRedirect();
        $this->assertNotNull($task->fresh()->done_at);
        $this->assertNull($lead->fresh()->next_follow_up_on);

        // Lost needs a reason; Won links the account and earns the partner commission
        $this->post(route('corral.crm.leads.stage', $lead), ['stage' => 'lost'])->assertSessionHasErrors('lost_reason');
        $c = Customer::firstOrFail();
        $this->post(route('corral.crm.leads.stage', $lead), ['stage' => 'won', 'account' => $c->account])->assertRedirect();
        $lead->refresh();
        $this->assertSame('won', $lead->stage);
        $this->assertSame($c->id, $lead->customer_id);
        $this->assertNotNull($lead->closed_at);
        $this->assertSame(round(50000 * $partner->commission_rate, 2), $partner->commissionFor($lead));
        $this->assertTrue($lead->activities()->where('type', 'stage')->where('subject', 'Contacted → Won')->exists());
        $this->get(route('corral.crm.partners.show', $partner))->assertSee('Sample Feed Store');
    }
}
