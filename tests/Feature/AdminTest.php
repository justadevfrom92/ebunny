<?php

namespace Tests\Feature;

use App\Models\AdminApp;
use App\Models\ByopProduct;
use App\Models\ContactMessage;
use App\Models\Customer;
use App\Models\HistoryItem;
use App\Models\Market;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\ReportRun;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function user(string $email = 'admin@example.com'): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/corral')->assertRedirect('/admin/login');
    }

    public function test_sign_in_with_the_seeded_password_and_reject_bad_or_disabled_accounts(): void
    {
        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/admin/login', ['email' => 'former@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/admin/login', ['email' => 'admin@example.com', 'password' => 'password'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($this->user());
    }

    public function test_every_admin_screen_renders_for_an_administrator(): void
    {
        $this->actingAs($this->user());
        $customer = Customer::first();
        $plan = Plan::first();

        $urls = ['/admin', '/admin/corral', '/admin/corral?q=houston', '/admin/corral?exception=*&limit=0',
            '/admin/corral/customers/'.$customer->account, '/admin/corral/esiid?q=Example', '/admin/corral/orders/create',
            '/admin/corral/orders/create-biz', '/admin/corral/orders/create?renew='.$customer->account,
            '/admin/walker/activity/orders', '/admin/corral/queues',
            '/admin/corral/queues/unapplied-payments', '/admin/corral/messages',
            '/admin/lando/pages', '/admin/lando/pages/create', '/admin/lando/pages/1/edit', '/admin/lando/templates',
            '/admin/lando/blocks', '/admin/lando/blocks/create', '/admin/lando/blocks/8/edit', '/admin/astro/plans',
            '/admin/astro/plans/create', '/admin/astro/plans/'.$plan->id.'/edit', '/admin/astro/groups',
            '/admin/astro/rates', '/admin/astro/rates?plan=JEY&kwh=500', '/admin/astro/rates/edit', '/admin/astro/fees', '/admin/sheriff/markets',
            '/admin/astro/terms', '/admin/astro/etfs', '/admin/astro/products',
            '/admin/sheriff/users', '/admin/sheriff/users/1/history', '/admin/sheriff/users/1/history?hgroup=Logins', '/admin/sheriff/roles', '/admin/sheriff/integrations', '/admin/sheriff/jobs'];
        // Each reference table opens in the app that owns it, and only there
        foreach (config('admin.reference_tables') as $table => $t) {
            if ($table !== 'monthly-drawing') {   // Drawing Winners has its own pages (Bounty → Drawing Winners)
                $urls[] = '/admin/'.$t[2].'/data/'.$table;
            }
        }
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail())->get('/admin/bounty/data/monthly-drawing')->assertRedirect(route('bounty.winners'));
        $this->get('/admin/sheriff/data/tax-rates')->assertNotFound();
        // Fraud Indicators and CIS Note Dispositions are Finance's (Caboose), not Sheriff's
        $this->get('/admin/caboose/data/fraud-indicators')->assertOk()->assertSee('Fraud Indicators');
        $this->get('/admin/caboose/data/note-dispositions')->assertOk()->assertSee('CIS Note Dispositions');
        $this->get('/admin/sheriff/data/fraud-indicators')->assertNotFound();
        $this->get('/admin/sheriff')->assertRedirect();
        $this->followingRedirects()->get('/admin/sheriff')->assertDontSee('Fraud Indicators');

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_admin_button_in_each_app_leads_back_to_the_launcher(): void
    {
        $this->actingAs($this->user())->get('/admin/astro/plans')
            ->assertSee('class="admin-home" href="'.route('admin.launcher').'"', false)
            ->assertDontSee('app-switch')
            ->assertDontSee('Find a menu item');
    }

    public function test_changes_are_recorded_in_history_with_old_and_new_values(): void
    {
        $admin = $this->user();
        $c = Customer::where('status', 'Submitted')->firstOrFail();

        $this->actingAs($admin)->patch('/admin/corral/customers/'.$c->account.'/status', ['status' => 'Good - On Flow'])->assertRedirect();

        $item = HistoryItem::where('customer_id', $c->id)->where('model', 'TicketCustomer_model')->where('action', 'updated')->latest('id')->firstOrFail();
        $this->assertSame(['Submitted', 'Good - On Flow'], $item->changes['status']);
        $this->assertSame($admin->id, $item->user_id);
        $this->assertTrue(HistoryItem::where('customer_id', $c->id)->where('model', 'ItemNote_model')->where('action', 'created')->exists());

        // The account page links to the ticket and to each log, and each of those is its own page
        $this->get('/admin/corral/customers/'.$c->account)->assertOk()
            ->assertSee(route('corral.customers.ticket', $c))->assertSee(route('corral.customers.log', [$c, 'attributes']));
        $this->get('/admin/corral/customers/'.$c->account.'/ticket')->assertOk()->assertSee('TicketCustomer_model')->assertSee('Child Tickets');
        $this->get('/admin/corral/customers/'.$c->account.'/logs/attributes')->assertOk()->assertSee('TicketCustomer_model');
        $this->get('/admin/corral/customers/'.$c->account.'/logs/ercot')->assertOk()->assertSee('ItemErcot81405_model');
        $this->get('/admin/corral/customers/'.$c->account.'/logs/not-a-log')->assertNotFound();
        $this->get('/admin/corral/history/'.$item->id)->assertOk()->assertSee('Good - On Flow')->assertSee('Process Logs');
        $this->get('/admin/sheriff/users/'.$admin->id.'/history')->assertOk()->assertSee('TicketCustomer_model');
        $this->get('/admin/sheriff/history/'.$item->id)->assertOk();
    }

    public function test_password_changes_are_not_stored_in_history(): void
    {
        $admin = $this->user();
        $csr = $this->user('csr@example.com');
        $this->actingAs($admin)->patch('/admin/sheriff/users/'.$csr->id, ['password' => 'a-brand-new-password'])->assertRedirect();

        $item = HistoryItem::where('model', 'User_model')->where('record_id', $csr->id)->latest('id')->firstOrFail();
        $this->assertSame(['(hidden)', '(changed)'], $item->changes['password']);
        $this->assertArrayNotHasKey('password', $item->data);
        $this->assertStringNotContainsString('a-brand-new-password', json_encode($item->toArray()));
    }

    public function test_new_admin_app_can_be_created_opened_edited_and_deleted(): void
    {
        $admin = $this->user();
        $this->actingAs($admin)->get('/admin')->assertSee('New Admin App');
        $this->get('/admin/apps/create')->assertOk();

        $finance = Role::where('name', 'Finance')->first();
        $this->post('/admin/apps', [
            'name' => 'Marketing', 'description' => 'Campaign tools', 'icon' => 'bolt', 'roles' => [$finance->id],
            'menu' => [
                ['heading' => 'Website', 'label' => 'Content Blocks', 'route' => 'lando.blocks.index', 'url' => ''],
                ['heading' => 'Links', 'label' => 'Analytics', 'route' => '', 'url' => 'https://example.com/analytics'],
                ['heading' => '', 'label' => '', 'route' => '', 'url' => ''],
            ],
        ])->assertRedirect('/admin/marketing');

        $app = AdminApp::where('key', 'marketing')->firstOrFail();
        $this->assertCount(2, $app->menu);
        $this->assertContains('marketing', Role::where('name', 'Administrator')->first()->perms);
        $this->assertContains('marketing', $finance->fresh()->perms);
        $this->assertTrue(HistoryItem::where('model', 'AdminApp_model')->where('action', 'created')->exists());

        $this->actingAs($admin = $admin->fresh()); // each real request loads the user (and role) fresh
        $this->get('/admin')->assertSee('Marketing')->assertSee('Campaign tools');
        $this->get('/admin/marketing')->assertOk()->assertSee('Content Blocks')->assertSee(route('lando.blocks.index'))->assertSee('https://example.com/analytics');
        $this->get('/admin/sheriff/roles')->assertSee('Marketing');

        // Roles without it are sent back to the launcher
        $this->actingAs($this->user('csr@example.com'))->get('/admin/marketing')->assertRedirect('/admin?denied=marketing');

        // Edit, then delete
        $this->actingAs($admin)->put('/admin/apps/marketing', ['name' => 'Marketing Team', 'description' => 'Campaigns', 'icon' => 'star', 'roles' => [], 'menu' => []])
            ->assertRedirect('/admin/marketing');
        $this->assertNotContains('marketing', $finance->fresh()->perms);
        $this->delete('/admin/apps/marketing')->assertRedirect('/admin');
        $this->assertNull(AdminApp::where('key', 'marketing')->first());
        $this->assertNotContains('marketing', Role::where('name', 'Administrator')->first()->perms);
    }

    public function test_new_apps_cannot_reuse_built_in_addresses_and_need_sheriff(): void
    {
        $this->actingAs($this->user())->post('/admin/apps', ['name' => 'Corral', 'description' => 'x', 'icon' => 'folder'])->assertSessionHasErrors('key');
        $this->post('/admin/apps', ['name' => 'Bad link', 'description' => 'x', 'icon' => 'folder', 'menu' => [['label' => 'x', 'url' => 'javascript:alert(1)']]])
            ->assertSessionHasErrors('menu.0.url');

        $this->actingAs($this->user('pricing@example.com'))->get('/admin')->assertDontSee('New Admin App');
        $this->get('/admin/apps/create')->assertForbidden();
    }

    public function test_roles_limit_which_apps_a_user_can_open(): void
    {
        $this->actingAs($this->user('pricing@example.com'));

        $this->get('/admin/astro/plans')->assertOk();
        $this->get('/admin/astro/terms')->assertOk();
        $this->get('/admin/corral')->assertRedirect('/admin?denied=corral');
        $this->get('/admin/sheriff/users')->assertRedirect('/admin?denied=sheriff');
        // The launcher only shows the apps they can open
        $this->get('/admin')->assertSee('Open Lando')->assertSee('Open Astro')->assertDontSee('Open Corral')->assertDontSee('No access');
        $this->get('/admin?denied=corral')->assertSee("You don't have access to Corral", false);
    }

    public function test_refunds_and_deletes_need_their_rights(): void
    {
        $payment = Payment::where('status', 'Success')->first();

        $this->actingAs($this->user('csr@example.com'))
            ->post('/admin/corral/payments/'.$payment->id.'/reverse')->assertForbidden();

        $before = $payment->customer->balance;
        $this->actingAs($this->user())->post('/admin/corral/payments/'.$payment->id.'/reverse')->assertRedirect();
        $this->assertSame('Reversed', $payment->fresh()->status);
        $this->assertEqualsWithDelta($before + $payment->amount, $payment->customer->fresh()->balance, 0.001);
    }

    public function test_rate_changes_reach_the_website_catalog_on_their_effective_date(): void
    {
        $this->actingAs($this->user());
        $plan = Plan::where('internal', 'CT12')->first();
        $oncor = Market::where('name', 'TX-E-ONCOR')->first();

        $this->put('/admin/astro/rates', ['effective_on' => now()->addDay()->toDateString(), 'rates' => [$plan->id => [$oncor->id => '9.999']]])->assertRedirect();
        $this->get('/shared/config/catalog.js')->assertOk()->assertDontSee('"energy":9.999', false);

        $this->put('/admin/astro/rates', ['effective_on' => today()->toDateString(), 'rates' => [$plan->id => [$oncor->id => '5.000']]])->assertRedirect();
        $this->get('/shared/config/catalog.js')->assertSee('"plan":"CT12","market":"TX-E-ONCOR","energy":5', false);
    }

    public function test_hidden_byop_products_and_plan_edits_show_in_the_catalog(): void
    {
        $this->actingAs($this->user());
        $product = ByopProduct::where('key', 'peakperks')->first();
        $plan = Plan::where('internal', 'G18')->first();

        $this->put('/admin/astro/products', ['p' => [$product->id => ['rate_adj' => '-0.5', 'monthly' => '0', 'active' => '0']]])->assertRedirect();
        $this->put('/admin/astro/plans/'.$plan->id, [
            'name' => 'The Gruene 18', 'internal' => 'G18', 'slug' => '', 'active' => 1, 'type' => 'Resi', 'term' => 18,
            'rolloff' => 'JEY3', 'etf' => '$275', 'mrc' => 4.95, 'green' => 100, 'tags' => 'fixed, green', 'perks' => "Solar from West Texas\nFixed for 18 months",
        ])->assertRedirect('/admin/astro/plans');

        $this->get('/shared/config/catalog.js')
            ->assertSee('"key":"peakperks","name":"Peak Perks","model":"ItemProductPeakPerk_model","type":"Discount","adj":-0.5,"monthly":0,"step":"Lower Your Bill","active":false', false)
            ->assertSee('Solar from West Texas');
    }

    public function test_phone_orders_create_an_account_and_queue_item(): void
    {
        $this->actingAs($this->user());
        $plan = Plan::where('internal', 'PP12')->first();

        $this->post('/admin/corral/orders', [
            'biz' => 0, 'name' => 'Test Person', 'phone' => '(555) 555-0199', 'email' => 'test@example.com',
            'address' => '12 Test St', 'city' => 'Houston', 'zip' => '77002', 'esiid' => '', 'market_id' => Market::first()->id,
            'plan_id' => $plan->id, 'enrollment_type' => 'Switch', 'autopay' => 1, 'paperless' => 0, 'peak_perks' => 0, 'source' => 'Phone',
        ])->assertRedirect();

        $c = Customer::where('email', 'test@example.com')->firstOrFail();
        $this->assertSame('Submitted', $c->status);
        $this->assertSame('No ESIID', $c->exception);
        $this->assertTrue(WorkItem::open()->where('queue', 'unprocessed-orders')->where('customer_id', $c->id)->exists());
    }

    public function test_website_contact_form_lands_in_corral(): void
    {
        $before = ContactMessage::open()->count();   // the sample data has some open messages
        $this->postJson('/contact', ['first' => 'Pat', 'last' => 'Example', 'email' => 'pat@example.com', 'topic' => 'general', 'message' => 'Hello'])
            ->assertCreated();
        $this->assertSame($before + 1, ContactMessage::open()->count());

        $this->actingAs($this->user())->get('/admin/corral/messages')->assertSee('Pat Example');
    }

    public function test_csv_report_downloads(): void
    {
        $this->actingAs($this->user())
            ->get('/admin/walker/activity/orders?run=1&format=csv&start=2026-01-01&end='.today()->toDateString())->assertRedirect();
        $run = ReportRun::where('report', 'orders')->latest('id')->firstOrFail();
        $this->get(route('admin.files.download', $run))->assertOk()->assertDownload(basename($run->file));
        $this->get(route('admin.files.show', $run))->assertOk()->assertSee('CSV');
    }

    public function test_users_cannot_lock_themselves_out(): void
    {
        $admin = $this->user();
        $this->actingAs($admin)->patch('/admin/sheriff/users/'.$admin->id, ['active' => 0])->assertSessionHasErrors('user');
        $this->assertTrue($admin->fresh()->active);
    }

    public function test_website_pages_and_data_load(): void
    {
        $this->get('/')->assertOk();
        $this->get('/shared/config/brand.js')->assertOk()->assertSee('ET.brand');
        $this->getJson('/site/session')->assertOk()->assertJsonStructure(['csrf', 'user']);
    }
}
