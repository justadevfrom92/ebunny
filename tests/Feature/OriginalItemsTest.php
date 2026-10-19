<?php

namespace Tests\Feature;

use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\HistoryItem;
use App\Models\Payment;
use App\Models\User;
use App\Support\Items;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every original Corral item model (config/items.php) has its own page with its fields, buttons and tickets. */
class OriginalItemsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    public function test_every_original_model_has_a_page_with_its_fields(): void
    {
        $this->actingAs($this->admin());
        $this->assertCount(49, config('items.models'));
        $customers = Customer::orderBy('id')->get();
        foreach (config('items.models') as $model => $d) {
            if ($d['source'] === 'ticket') {
                $this->get(route('corral.items.show', [$customers->first()->id, $model]))->assertRedirect();

                continue;
            }
            $record = $customers->map(fn ($c) => Items::forModel($model, $c)->first())->filter()->first();
            $this->assertNotNull($record, "$model has no sample record");
            $html = $this->get(route('corral.items.show', [$record->getKey(), $model]))->assertOk()->getContent();
            foreach ($d['fields'] as $f) {
                $this->assertStringContainsString('<td>'.$f.'</td>', $html, "$model page is missing $f");
            }
            foreach ($d['actions'] as $a) {
                $this->assertStringContainsString(e(config('items.actions.'.$a)), $html, "$model page is missing its $a button");
            }
            $this->get(route('corral.items.edit', [$record->getKey(), $model]))->assertOk();
        }
    }

    public function test_tickets_list_their_items_and_secrets_stay_hidden(): void
    {
        $this->actingAs($this->admin());
        $c = Customer::where('account', '1219000000')->firstOrFail();
        $this->get(route('corral.customers.ticket', $c))->assertOk()->assertSee('ItemPersonShopper_model')->assertSee('ItemLocationPostalUsa_model')->assertSee('Billing Address');
        $this->get(route('corral.customers.log', [$c, 'products']))->assertOk()->assertSee('ItemProductAutopay_model');
        $this->get(route('corral.customers.log', [$c, 'emails']))->assertOk()->assertSee('ItemEmailEnergytexasWelcome_model');
        $this->get(route('corral.customers.log', [$c, 'attributes']))->assertOk()->assertSee('pool = ');

        $c->update(['ssn_last4' => '4321']);
        $html = $this->get(route('corral.items.show', [$c->id, 'ItemPersonShopper_model']))->assertOk()->getContent();
        $this->assertStringContainsString('•••• 4321', $html);
        $this->get(route('corral.items.show', [$c->id, 'ItemMyaccountLogin_model']))->assertOk()->assertSee('(set — hidden)')->assertDontSee($c->password);
    }

    public function test_edit_saves_columns_and_original_only_fields_and_logs_it(): void
    {
        $this->actingAs($this->admin());
        $p = Payment::firstOrFail();
        $this->put(route('corral.items.update', [$p->id, 'ItemPayment_model']), ['f' => ['confirmation_number' => 'CONF-9', 'journal_entry_id' => 'JE-77', 'amount' => '999']])->assertRedirect();
        $this->assertSame('CONF-9', $p->fresh()->confirmation);
        $this->assertNotSame(999.0, $p->fresh()->amount);   // amounts change through Caboose, not here
        $html = $this->get(route('corral.items.show', [$p->id, 'ItemPayment_model']))->getContent();
        $this->assertStringContainsString('JE-77', $html);
        $this->assertTrue(HistoryItem::where('model', 'ItemPayment_model')->where('record_id', $p->id)->where('summary', 'like', 'Edited:%')->exists());
    }

    public function test_buttons_do_their_jobs(): void
    {
        $this->actingAs($this->admin());
        $email = ContactLog::where('channel', 'Email')->where('template', 'Welcome Letter')->firstOrFail();
        $this->post(route('corral.items.act', [$email->id, 'ItemEmailEnergytexasWelcome_model', 'resend']))->assertRedirect()->assertSessionHas('item_notice');
        $this->assertSame(2, ContactLog::where('customer_id', $email->customer_id)->where('template', 'Welcome Letter')->count());

        $p = Payment::firstOrFail();
        $c = $p->customer;
        $held = (float) $c->deposit_held;
        $this->post(route('corral.items.act', [$p->id, 'ItemPayment_model', 'duplicate']))->assertRedirect();
        $this->assertSame('Pending', Payment::latest('id')->first()->status);
        $this->post(route('corral.items.act', [$p->id, 'ItemPayment_model', 'mark_deposit']))->assertRedirect();
        $this->assertEqualsWithDelta($held + $p->amount, (float) $c->fresh()->deposit_held, 0.001);
        $this->post(route('corral.items.act', [$p->id, 'ItemPayment_model', 'resend']))->assertNotFound();   // not a payment button

        $bill = $c->bills()->firstOrFail();
        $this->post(route('corral.items.act', [$bill->id, 'ItemFileBill_model', 'check_account']))->assertSessionHas('item_notice');

        // Account-level items can't be deleted on their own; someone without Corral can't delete at all
        $this->delete(route('corral.items.destroy', [$c->id, 'ItemPersonShopper_model']))->assertStatus(422);
        $this->actingAs(User::where('email', 'pricing@example.com')->firstOrFail());
        $this->delete(route('corral.items.destroy', [$p->id, 'ItemPayment_model']))->assertRedirect();
        $this->assertNotNull(Payment::find($p->id));
    }
}
