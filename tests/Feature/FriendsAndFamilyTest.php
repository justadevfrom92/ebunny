<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LeadApproval;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Friends & Family accounts: money and service changes from someone who isn't a Lead go to a Lead for approval. */
class FriendsAndFamilyTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function account(): Customer
    {
        return Customer::whereHas('flags', fn ($q) => $q->where('flag', 'VIP - Friends & Family'))->firstOrFail();
    }

    private function csr(): User
    {
        return User::where('email', 'csr@example.com')->first();
    }

    private function lead(): User
    {
        return User::where('email', 'lead@example.com')->first();
    }

    public function test_banner_just_says_who_they_are(): void
    {
        $this->actingAs($this->csr())->get(route('corral.customers.show', $this->account()))->assertOk()
            ->assertSee('Friends and Family')->assertSee('friend or relative')
            ->assertDontSee('back office')->assertDontSee('Lead only')->assertDontSee('You can make these changes');
        $this->assertFalse($this->csr()->isLead());
        $this->assertTrue($this->lead()->isLead());
        $this->assertTrue(User::where('email', 'admin@example.com')->first()->isLead());
    }

    public function test_request_then_lead_approves_and_the_change_is_made(): void
    {
        $c = $this->account();
        $this->actingAs($this->csr())->get(route('corral.customers.action', [$c, 'add-bill-credit']))->assertOk()->assertSee('Request Lead Approval');
        $this->post(route('corral.customers.action.run', [$c, 'add-bill-credit']), ['amount' => 25, 'description' => 'Favor'])->assertForbidden();
        $this->post(route('corral.customers.action.request', [$c, 'add-bill-credit']), ['amount' => 25, 'description' => 'Courtesy credit', 'approval_note' => 'Outage last week'])
            ->assertRedirect(route('corral.customers.show', $c).'#actions');
        $a = LeadApproval::latest('id')->first();
        $this->assertSame(['pending', 'add-bill-credit', 'Outage last week'], [$a->status, $a->action, $a->note]);
        $this->assertSame(1, $this->lead()->adminNotifications()->where('kind', 'approval')->count());
        $this->get(route('corral.customers.show', $c))->assertSee('Waiting for a Lead')->assertSee('#'.$a->id);
        $this->get(route('corral.approvals'))->assertOk()->assertSee('#'.$a->id);
        $this->post(route('corral.approvals.approve', $a))->assertForbidden();   // not a Lead

        $credits = $c->ledgerEntries()->where('kind', 'credit')->count();
        $this->actingAs($this->lead())->get(route('corral.approvals'))->assertSee('#'.$a->id);
        $this->get(route('corral.approvals.show', $a))->assertSee('Approve')->assertSee('Deny');
        $this->post(route('corral.approvals.approve', $a))->assertRedirect(route('corral.approvals.show', $a));
        $this->assertSame('approved', $a->fresh()->status);
        $this->assertSame($credits + 1, $c->ledgerEntries()->where('kind', 'credit')->count());
        $this->assertSame(1, $this->csr()->adminNotifications()->where('kind', 'approval')->count());
    }

    public function test_lead_can_deny_with_a_reason(): void
    {
        $c = $this->account();
        $this->actingAs($this->csr())->post(route('corral.customers.action.request', [$c, 'disconnect']), ['date' => today()->toDateString()]);
        $a = LeadApproval::latest('id')->first();
        $this->actingAs($this->lead())->post(route('corral.approvals.deny', $a))->assertSessionHasErrors('decision_note');
        $this->post(route('corral.approvals.deny', $a), ['decision_note' => 'Customer paid'])->assertRedirect(route('corral.approvals'));
        $this->assertSame(['denied', 'Customer paid'], [$a->fresh()->status, $a->fresh()->decision_note]);
    }

    public function test_pending_charge_delete_goes_to_a_lead(): void
    {
        $c = $this->account();
        $e = LedgerEntry::create(['customer_id' => $c->id, 'kind' => 'debit', 'amount' => 50, 'description' => 'Late fee', 'status' => 'pending']);
        $this->actingAs($this->csr())->get(route('corral.customers.show', $c))->assertSee('Request Delete');
        $this->delete(route('corral.ledger.delete', $e))->assertForbidden();
        $this->post(route('corral.ledger.request-delete', $e))->assertRedirect();
        $a = LeadApproval::latest('id')->first();
        $this->actingAs($this->lead())->post(route('corral.approvals.approve', $a));
        $this->assertSame('deleted', $e->fresh()->status);
    }

    public function test_leads_and_other_accounts_work_as_before(): void
    {
        $c = $this->account();
        $this->actingAs($this->lead())->get(route('corral.customers.action', [$c, 'add-bill-credit']))->assertOk()->assertDontSee('Request Lead Approval');
        $other = Customer::whereDoesntHave('flags', fn ($q) => $q->where('flag', 'VIP - Friends & Family'))->firstOrFail();
        $this->actingAs($this->csr())->get(route('corral.customers.action', [$other, 'add-bill-credit']))->assertOk()->assertDontSee('Request Lead Approval');
    }
}
