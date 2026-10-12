<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\RewardOffer;
use App\Models\Signup;
use App\Models\WorkItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Website sign-up (checkout) and the My Account customer portal. */
class WebsiteAccountTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_sign_up_from_address_to_accepted_creates_an_order(): void
    {
        $plan = Plan::where('internal', 'CT12')->firstOrFail();
        $this->get('/checkout?plan=CT12&zip=77002&msid=3001&ref=AVER0000')->assertOk()->assertSee('Where do you need service?');

        $this->post('/checkout/address', ['zip' => '99999', 'address' => '1 Main', 'city' => 'X', 'enrollment_type' => 'Switch'])->assertSessionHasErrors('zip');
        $this->post('/checkout/address', ['biz' => 0, 'zip' => '77002', 'address' => '1200 Example St', 'city' => 'Houston', 'enrollment_type' => 'Move-In',
            'start_date' => today()->addDays(10)->toDateString()])->assertRedirect('/checkout/plan');
        $this->get('/checkout/plan')->assertOk()->assertSee($plan->name)->assertSee('checked', false);
        $this->post('/checkout/plan', ['plan_id' => $plan->id])->assertRedirect('/checkout/about');
        $this->get('/checkout/about')->assertOk();
        $this->post('/checkout/about', ['first_name' => 'Jamie', 'last_name' => 'Testcase', 'email' => 'jamie@example.com', 'phone' => '(555) 555-0199',
            'phone_type' => 'mobile', 'language' => 'English', 'ssn_last4' => '1234', 'username' => 'jamie.t', 'password' => 'a-good-password', 'password_confirmation' => 'a-good-password'])
            ->assertRedirect('/checkout/review');
        $this->get('/checkout/review')->assertOk()->assertSee('1,000 kWh');
        $this->post('/checkout/review', ['autopay' => 0])->assertSessionHasErrors(['agree_efl', 'agree_tos', 'agree_yrac']);
        $this->post('/checkout/review', ['agree_efl' => 1, 'agree_tos' => 1, 'agree_yrac' => 1, 'paperless' => 1])->assertRedirect('/checkout/accepted');

        $c = Customer::where('email', 'jamie@example.com')->firstOrFail();
        $this->assertSame(['Submitted', 'Website', 'Move-In', '3001', 'AVER0000', '1234'], [$c->status, $c->source, $c->enrollment_type, $c->msid, $c->referred_by, $c->ssn_last4]);
        $this->assertTrue($c->paperless);
        $this->assertTrue(WorkItem::where('customer_id', $c->id)->where('queue', 'unprocessed-orders')->exists());
        $this->assertSame(1, $c->planTerms()->count());
        $this->get('/checkout/accepted')->assertOk()->assertSee($c->account)->assertSee('Sign in to My Account');

        // The login made at sign-up works
        $this->post('/myaccount/login', ['login' => 'jamie.t', 'password' => 'a-good-password'])->assertRedirect('/myaccount/dashboard');
    }

    public function test_save_for_later_frozen_cancel_and_deposit_pages(): void
    {
        Mail::fake();
        $this->post('/checkout/address', ['zip' => '75201', 'address' => '9 Elm', 'city' => 'Dallas', 'enrollment_type' => 'Switch']);
        $this->post('/checkout/save', ['email' => 'later@example.com', 'step' => 'plan'])->assertRedirect('/checkout/saved');
        $token = Signup::firstOrFail()->token;
        $this->flushSession();
        $this->get('/checkout/resume/'.$token)->assertRedirect('/checkout/plan');
        $this->get('/checkout/plan')->assertOk()->assertSee('9 Elm');

        foreach (['alternatives', 'frozen', 'start-call', 'cancel', 'error'] as $page) {
            $this->get('/checkout/'.$page)->assertOk();
        }
        $c = Customer::where('status', 'Submitted')->latest('id')->firstOrFail();
        $c->forceFill(['created_at' => now()])->save();
        $this->post('/checkout/cancel', ['account' => $c->account, 'zip' => $c->zip, 'email' => 'wrong@example.com'])->assertSessionHasErrors('account');
        $this->post('/checkout/cancel', ['account' => $c->account, 'zip' => $c->zip, 'email' => $c->email])->assertRedirect('/checkout/cancel');
        $this->assertSame('Cancelled', $c->fresh()->status);

        $c->update(['deposit_due' => 150]);
        $this->get('/checkout/deposit?account='.$c->account.'&zip='.$c->zip)->assertOk()->assertSee('$150.00');
    }

    public function test_my_account_pages_and_self_service(): void
    {
        $this->get('/myaccount/dashboard')->assertRedirect('/myaccount/login');
        $this->post('/myaccount/login', ['login' => 'demo.customer', 'password' => 'nope'])->assertSessionHasErrors('login');
        $this->post('/myaccount/login', ['login' => 'demo.customer', 'password' => 'password'])->assertRedirect('/myaccount/dashboard');
        $c = Customer::where('username', 'demo.customer')->firstOrFail();
        // The website header shows Sign Out while a customer is signed in
        $this->getJson('/site/session')->assertJsonPath('customer.name', $c->first_name);
        $this->get('/shared/config/brand.js')->assertSee('"signoutTest":true', false); // test mode outside production

        foreach (['dashboard', 'bill-and-payments/view-bills', 'bill-and-payments/pay-bill', 'bill-and-payments/payment-methods', 'energy-insights',
            'products', 'profile-and-preferences', 'plan-and-services/current-plan',
            'plan-and-services/transfer-service', 'rewards', 'refer-a-friend', 'message-center'] as $page) {
            $this->get('/myaccount/'.$page)->assertOk()->assertSee($c->account);
        }

        // The old one-product pages open that product's card on Products, which lists every product
        $this->get('/myaccount/enroll/autopay')->assertRedirect(route('myaccount.products').'#autopay');
        $products = $this->get('/myaccount/products')->assertSee('Electric Vehicle Charger Protection Program')->assertSee('ADT Security');
        foreach (config('corral.products') as $name => $model) {
            $products->assertSee($name);
        }

        // Admin pages stay closed to customers
        $this->get('/admin')->assertRedirect('/admin/login');

        $this->post('/myaccount/enroll/peak-perks', ['enroll' => 1])->assertRedirect();
        $this->assertTrue($c->fresh()->peak_perks);
        $this->post('/myaccount/enroll/peak-perks', ['enroll' => 0]);
        $this->assertFalse($c->fresh()->peak_perks);

        $method = $c->paymentMethods()->whereNull('removed_at')->firstOrFail();
        $pay = ['choice' => 'other', 'amount' => 25, 'method' => $method->id, 'when' => 'now'];
        $this->post('/myaccount/bill-and-payments/pay-bill', $pay)->assertRedirect('/myaccount/bill-and-payments/view-bills');
        $this->assertTrue($c->payments()->where('amount', 25)->where('source', 'MyAccount')->where('status', 'Pending')->exists());
        $other = Customer::where('id', '!=', $c->id)->whereHas('paymentMethods')->first();
        $this->post('/myaccount/bill-and-payments/pay-bill', ['method' => $other->paymentMethods()->value('id')] + $pay)->assertSessionHasErrors('method');
        $this->delete('/myaccount/payment-methods/'.$other->paymentMethods()->value('id'))->assertNotFound();

        $this->put('/myaccount/profile-and-preferences', ['email' => 'new@example.com', 'phone' => '5555550100', 'phone_type' => 'mobile', 'language' => 'Spanish',
            'billing_street' => '1 Main', 'billing_city' => 'Houston', 'billing_state' => 'TX', 'billing_zip' => '77002'])->assertRedirect();
        $this->assertSame('Spanish', $c->fresh()->language);

        $this->post('/myaccount/authorized-users', ['name' => 'Pat Helper', 'phone' => '5555550111'])->assertRedirect();
        $this->assertSame('Pat Helper', $c->fresh()->authorized_users[0]['name']);

        $plan = Plan::where('internal', 'PP24')->firstOrFail();
        $this->post('/myaccount/plan-and-services/renew-plan', ['plan_id' => $plan->id, 'agree' => 1])->assertRedirect('/myaccount/plan-and-services/current-plan');
        $this->assertTrue($c->planTerms()->where('status', 'pending')->where('plan_id', $plan->id)->exists());

        $c->starEntries()->create(['reason' => 'Test', 'stars' => 100]);
        $c->syncStars();
        $this->post('/myaccount/rewards', ['offer' => RewardOffer::where('stars', 50)->value('id')])->assertSessionHasNoErrors();

        $this->put('/myaccount/change-password', ['current_password' => 'password', 'password' => 'another-password', 'password_confirmation' => 'another-password'])->assertRedirect();
        $this->post('/myaccount/logout')->assertRedirect('/myaccount/login');
        $this->post('/myaccount/login', ['login' => 'new@example.com', 'password' => 'another-password'])->assertRedirect('/myaccount/dashboard');
    }

    public function test_create_login_forgot_password_and_quickpay(): void
    {
        Mail::fake();
        $c = Customer::whereNull('password')->firstOrFail();
        $this->post('/myaccount/create-account', ['account' => $c->account, 'zip' => $c->zip, 'email' => 'not-theirs@example.com',
            'username' => 'newuser', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertSessionHasErrors('account');
        $this->post('/myaccount/create-account', ['account' => $c->account, 'zip' => $c->zip, 'email' => $c->email,
            'username' => 'newuser', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertRedirect('/myaccount/dashboard');
        $this->post('/myaccount/logout');

        $this->post('/myaccount/forgot-password', ['login' => 'newuser'])->assertSessionHas('status');
        $this->post('/myaccount/forgot-password', ['login' => 'nobody'])->assertSessionHas('status'); // same answer either way
        $this->assertDatabaseHas('customer_password_resets', ['customer_id' => $c->id]);

        $this->get('/myaccount/quickpay?account='.$c->account.'&zip='.$c->zip)->assertOk()->assertSee(number_format($c->balance, 2));
        $this->get('/myaccount/quickpay?account='.$c->account.'&zip=00000')->assertOk()->assertSee('couldn');
    }
}
