<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Friends & Family accounts: money and service changes are Customer Service Lead only (conflict of interest). */
class FriendsAndFamilyTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function account(): Customer
    {
        return Customer::whereHas('flags', fn ($q) => $q->where('flag', 'VIP - Friends & Family'))->firstOrFail();
    }

    public function test_agents_cannot_give_credits_or_disconnect_but_leads_can(): void
    {
        $c = $this->account();
        $csr = User::where('email', 'csr@example.com')->first();
        $lead = User::where('email', 'lead@example.com')->first();
        $this->assertFalse($csr->hasPerm('vip_accounts'));
        $this->assertTrue($lead->hasPerm('vip_accounts'));

        $this->actingAs($csr)->get(route('corral.customers.show', $c))->assertOk()
            ->assertSee('Billing - Add Bill Credit (Lead only)')->assertSee('Status - Disconnect (Lead only)')
            ->assertSee('ask a Customer Service Lead')->assertDontSee('back office');
        $this->get(route('corral.customers.action', [$c, 'add-bill-credit']))->assertRedirect()->assertSessionHasErrors('action');
        $this->post(route('corral.customers.action.run', [$c, 'add-bill-credit']), ['amount' => 25, 'description' => 'Favor'])->assertForbidden();
        // Everything else still works for agents
        $this->get(route('corral.customers.action', [$c, 'send-text']))->assertOk();

        $this->actingAs($lead)->get(route('corral.customers.show', $c))->assertSee('You can make these changes.')->assertDontSee('(Lead only)');
        $this->post(route('corral.customers.action.run', [$c, 'add-bill-credit']), ['amount' => 25, 'description' => 'Approved by lead'])->assertRedirect();
    }

    public function test_agents_cannot_delete_pending_charges_on_the_account(): void
    {
        $c = $this->account();
        $e = LedgerEntry::create(['customer_id' => $c->id, 'kind' => 'debit', 'amount' => 50, 'description' => 'Late fee', 'status' => 'pending']);
        $this->actingAs(User::where('email', 'csr@example.com')->first())->delete(route('corral.ledger.delete', $e))->assertForbidden();
        $this->assertSame('pending', $e->fresh()->status);
        $this->actingAs(User::where('email', 'lead@example.com')->first())->delete(route('corral.ledger.delete', $e))->assertRedirect();
        $this->assertSame('deleted', $e->fresh()->status);
    }

    public function test_other_accounts_are_unchanged_for_agents(): void
    {
        $other = Customer::whereDoesntHave('flags', fn ($q) => $q->where('flag', 'VIP - Friends & Family'))->firstOrFail();
        $this->actingAs(User::where('email', 'csr@example.com')->first())->get(route('corral.customers.show', $other))->assertDontSee('(Lead only)');
    }
}
