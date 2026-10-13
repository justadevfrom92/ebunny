<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\ScheduledPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** My Account Pay Bill (amount choices, pay at a picked time), business properties, the menu and the Products page. */
class MyAccountPayTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_pay_bill_offers_amounts_and_schedules_a_payment(): void
    {
        $c = Customer::where('username', 'test.pastdue')->firstOrFail();
        $this->post(route('myaccount.login.test', $c));
        $method = PaymentMethod::where('customer_id', $c->id)->whereNull('removed_at')->first()
            ?? PaymentMethod::create(['customer_id' => $c->id, 'type' => 'Credit Card', 'vendor' => 'Stripe', 'last4' => '4242', 'expires' => '01/2031', 'autopay' => false]);

        $this->get(route('myaccount.pay'))->assertOk()->assertSee('Current balance')->assertSee('Last bill')->assertSee('Another amount')
            ->assertSee('Pay at a date and time I pick')->assertSee('$'.number_format($c->balance, 2));

        // Current balance, now
        $this->post(route('myaccount.pay.store'), ['choice' => 'balance', 'method' => $method->id, 'when' => 'now'])->assertRedirect(route('myaccount.bills'));
        $this->assertTrue($c->payments()->where('amount', $c->balance)->exists());

        // Another amount needs an amount; later needs a date and time in the future
        $this->post(route('myaccount.pay.store'), ['choice' => 'other', 'method' => $method->id, 'when' => 'now'])->assertSessionHasErrors('amount');
        $this->post(route('myaccount.pay.store'), ['choice' => 'other', 'amount' => 40, 'method' => $method->id, 'when' => 'later'])->assertSessionHasErrors(['pay_date', 'pay_time']);
        $this->post(route('myaccount.pay.store'), ['choice' => 'other', 'amount' => 40, 'method' => $method->id, 'when' => 'later', 'pay_date' => today()->addDays(90)->toDateString(), 'pay_time' => '09:00'])->assertSessionHasErrors('pay_date');

        $when = now()->addDays(3)->setTime(14, 30);
        $this->post(route('myaccount.pay.store'), ['choice' => 'other', 'amount' => 40, 'method' => $method->id, 'when' => 'later', 'pay_date' => $when->toDateString(), 'pay_time' => '14:30'])
            ->assertRedirect(route('myaccount.pay'))->assertSessionHas('status', fn ($s) => str_contains($s, 'scheduled'));
        $s = ScheduledPayment::where('customer_id', $c->id)->firstOrFail();
        $this->assertSame([40.0, $when->format('Y-m-d H:i'), 'scheduled'], [(float) $s->amount, $s->pay_at->format('Y-m-d H:i'), $s->status]);
        $this->get(route('myaccount.pay'))->assertSee('Scheduled Payments')->assertSee($when->format('M j, Y'));

        // The cron pays it when the time comes
        $before = $c->payments()->count();
        $this->artisan('et:scheduled-payments')->assertSuccessful();
        $this->assertSame('scheduled', $s->fresh()->status);
        $this->travelTo($when->copy()->addMinute());
        $this->artisan('et:scheduled-payments')->assertSuccessful();
        $this->assertSame('paid', $s->fresh()->status);
        $this->assertSame($before + 1, $c->payments()->count());
        $this->travelBack();

        // Cancel one before it pays
        $this->post(route('myaccount.pay.store'), ['choice' => 'other', 'amount' => 10, 'method' => $method->id, 'when' => 'later', 'pay_date' => today()->addDay()->toDateString(), 'pay_time' => '08:00']);
        $next = ScheduledPayment::where('status', 'scheduled')->latest('id')->firstOrFail();
        $this->delete(route('myaccount.scheduled.cancel', $next))->assertRedirect();
        $this->assertSame('canceled', $next->fresh()->status);
    }

    public function test_business_login_switches_between_properties_and_pays_each(): void
    {
        $login = Customer::where('username', 'test.business')->firstOrFail();
        $this->assertCount(3, $login->linked_accounts);
        $props = $login->properties();
        $second = $props[1];
        $card = PaymentMethod::create(['customer_id' => $login->id, 'type' => 'Credit Card', 'vendor' => 'Stripe', 'last4' => '1111', 'expires' => '01/2031', 'autopay' => true]);
        $this->post(route('myaccount.login.test', $login));

        $this->get(route('myaccount.dashboard'))->assertOk()->assertSee('Your Properties')->assertSee('Properties (4)')->assertSee($second->account)->assertSee($second->propertyLabel());
        $this->post(route('myaccount.property', $second->account), ['to' => 'pay'])->assertRedirect(route('myaccount.pay'));
        $this->get(route('myaccount.pay'))->assertOk()->assertSee('Which property?')->assertSee('Paying this one')->assertSee('Account '.$second->account);

        // The login's card pays any of its properties; the payment lands on the property being viewed
        $this->post(route('myaccount.pay.store'), ['choice' => 'other', 'amount' => 12.34, 'method' => $card->id, 'when' => 'now'])->assertRedirect();
        $this->assertTrue($second->payments()->where('amount', 12.34)->exists());
        $this->assertFalse($login->payments()->where('amount', 12.34)->exists());

        // Not one of this login's properties
        $stranger = Customer::whereNotIn('account', [$login->account, ...$login->linked_accounts])->firstOrFail();
        $this->post(route('myaccount.property', $stranger->account))->assertNotFound();
    }

    public function test_menu_has_bills_and_products_and_puts_the_menu_after_the_page(): void
    {
        $c = Customer::where('username', 'demo.customer')->firstOrFail();
        $this->post(route('myaccount.login.test', $c));
        $html = $this->get(route('myaccount.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('<h4>Bills</h4>', $html);
        $this->assertStringNotContainsString('<h4>Products</h4>', $html);
        $this->assertStringNotContainsString('Bill &amp; Payments', $html);
        // One Products & Protections link, right above Message Center
        $nav = substr($html, strpos($html, 'class="ma-nav"'));
        $nav = substr($nav, 0, strpos($nav, '</nav>'));
        $this->assertSame(1, substr_count($nav, 'href="'.route('myaccount.products').'"'));
        $this->assertSame(0, substr_count($nav, route('myaccount.products').'#'));
        $this->assertLessThan(strpos($nav, '>Message Center<'), strpos($nav, '>Products &amp; Protections<'));
        // On phones the account menu shows below the page: it comes after the page in the HTML
        $this->assertLessThan(strpos($html, 'class="ma-nav"'), strpos($html, 'class="ma-main"'));

        $this->post(route('myaccount.product.toggle', 'home-warranty'), ['enroll' => 1])->assertRedirect(route('myaccount.products').'#home-warranty');
        $this->assertTrue($c->hasProduct('AIG Home Warranty'));
        $this->post(route('myaccount.product.toggle', 'adt-security'), ['enroll' => 1])->assertNotFound();   // set up by phone, not a toggle
    }

    public function test_view_bills_opens_each_bill_as_a_pdf(): void
    {
        $c = Customer::where('username', 'demo.customer')->firstOrFail();
        $this->post(route('myaccount.login.test', $c));
        $bill = $c->bills()->orderByDesc('billed_on')->firstOrFail();
        $this->get(route('myaccount.bills'))->assertOk()->assertSee('View PDF')->assertSee('href="'.route('myaccount.bill.pdf', $bill).'" target="_blank"', false);
        $res = $this->get(route('myaccount.bill.pdf', $bill))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $res->getContent());
        $this->assertStringContainsString($c->account, $res->getContent());
        $this->assertStringContainsString('inline; filename="bill-', $res->headers->get('Content-Disposition'));
        // Someone else's bill
        $other = Bill::where('customer_id', '!=', $c->id)->firstOrFail();
        $this->get(route('myaccount.bill.pdf', $other))->assertNotFound();
    }

    public function test_message_center_has_a_page_per_message(): void
    {
        $c = Customer::where('username', 'demo.customer')->firstOrFail();
        $this->post(route('myaccount.login.test', $c));
        $list = $this->get(route('myaccount.messages'))->assertOk();
        foreach ($c->contactLogs()->limit(50)->get() as $m) {
            $list->assertSee(route('myaccount.message', $m), false);
            $this->get(route('myaccount.message', $m))->assertOk()->assertSee('What this is about')->assertSee('All messages');
        }
        $bill = $c->contactLogs()->where('template', 'Bill Ready')->firstOrFail();
        $this->get(route('myaccount.message', $bill))->assertSee('The bill')->assertSee('View PDF')->assertSee('srcdoc=', false);
        $other = ContactLog::where('customer_id', '!=', $c->id)->firstOrFail();
        $this->get(route('myaccount.message', $other))->assertNotFound();
    }
}
