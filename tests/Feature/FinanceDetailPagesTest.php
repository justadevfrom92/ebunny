<?php

namespace Tests\Feature;

use App\Http\Controllers\Backend\AuthController;
use App\Models\ApiLog;
use App\Models\LedgerEntry;
use App\Models\ReferenceRow;
use App\Models\Refund;
use App\Models\StarEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Caboose credit/debit and refund pages, Sheriff 3rd-party users, and sample rows for tables that used to be empty. */
class FinanceDetailPagesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function finance(): User
    {
        return User::where('email', 'finance@example.com')->firstOrFail();
    }

    public function test_credit_debit_page_and_decision(): void
    {
        $this->actingAs($this->finance());
        $e = LedgerEntry::where('status', 'pending')->firstOrFail();
        $this->get(route('caboose.ledger'))->assertOk()->assertSee(route('caboose.ledger.show', $e), false)->assertSee('Recently Decided')->assertDontSee('None yet.');
        $html = $this->get(route('caboose.ledger.show', $e))->assertOk()->assertSee('After Applying')->assertSee('Apply to Balance')->assertSee('Recent Payments')->getContent();
        $this->assertDoesNotMatchRegularExpression('#/admin/corral/(?!customers/\d+(/ticket|/logs/\w+)?")#', $html);   // only account links go to Corral

        $before = $e->customer->balance;
        $this->post(route('caboose.ledger.decide', $e), ['do' => 'apply', 'note' => 'Checked'])->assertRedirect();
        $e->refresh();
        $this->assertSame('applied', $e->status);
        $this->assertSame($this->finance()->id, $e->decided_by);
        $this->assertSame('Checked', $e->decision_note);
        $this->assertEqualsWithDelta($before + ($e->kind === 'credit' ? -$e->amount : $e->amount), $e->customer->fresh()->balance, 0.001);
        $this->get(route('caboose.ledger.show', $e))->assertSee('Finance Demo')->assertDontSee('Apply to Balance');
    }

    public function test_refund_page_through_each_step(): void
    {
        $this->actingAs($this->finance());
        foreach (['requested', 'approved', 'paid', 'rejected'] as $status) {
            $this->assertTrue(Refund::where('status', $status)->exists(), $status);
        }
        $this->get(route('caboose.refunds'))->assertOk()->assertDontSee('None yet.');
        $r = Refund::where('status', 'requested')->firstOrFail();
        $this->get(route('caboose.refunds.show', $r))->assertOk()->assertSee('Progress')->assertSee('Approve')->assertSee('Can Refund Now');
        $this->post(route('caboose.refunds.decide', $r), ['do' => 'approve', 'note' => 'Final bill confirmed'])->assertRedirect();
        $this->get(route('caboose.refunds.show', $r))->assertSee('Final bill confirmed')->assertSee('Mark Paid');
        $this->post(route('caboose.refunds.decide', $r), ['do' => 'paid', 'check_number' => '10777'])->assertRedirect();
        $this->assertSame('10777', $r->fresh()->check_number);
        $this->get(route('caboose.refunds.show', $r))->assertSee('Nothing left to do');
    }

    public function test_external_users_and_filled_tables(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
        $this->assertGreaterThan(25, User::count());
        $this->get(route('sheriff.users.index'))->assertOk()->assertSee('3rd party')->assertSee('Brightline Contact Solutions')->assertSee('Lone Prairie Recovery Services')->assertSee('Customer Service');
        $ext = User::whereNotNull('company')->firstOrFail();
        $this->get(route('sheriff.users.edit', $ext))->assertOk()->assertSee($ext->company)->assertSee('Access ends');
        // The test sign-in list stays the sample @example.com admins
        $this->assertTrue(AuthController::testUsers()->every(fn ($u) => str_ends_with($u->email, '@example.com')));

        $this->assertSame(12, ReferenceRow::where('table_key', 'monthly-drawing')->count());
        $this->get(route('bounty.drawing'))->assertOk()->assertDontSee('<td class="empty">None.</td>', false);
        $this->assertTrue(StarEntry::where('fulfillment', 'pending')->exists());
        foreach (config('admin.integrations') as $i) {
            $this->assertTrue(ApiLog::where('api', $i['name'])->exists(), $i['name']);
        }
    }
}
