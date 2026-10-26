<?php

namespace Tests\Feature;

use App\Models\AdminNotification;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Admin notifications: what makes them, the number and menu list, the Notifications page and Settings. */
class AdminNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_tickets_make_notifications_and_settings_turn_them_off(): void
    {
        $csr = User::where('email', 'csr@example.com')->firstOrFail();
        $c = Customer::whereNotNull('password')->firstOrFail();
        $before = $csr->adminNotifications()->count();

        // A new ticket tells the Ranger team; a reply tells whoever has it
        $this->actingAs($c, 'customer')->post(route('myaccount.support.store'), ['topic' => 'billing', 'subject' => 'Notify me', 'body' => 'Hello'])->assertRedirect();
        $t = SupportTicket::latest('id')->firstOrFail();
        $this->assertSame('ticket_new', $csr->adminNotifications()->value('kind'));
        $t->update(['assigned_to' => $csr->id]);
        $this->post(route('myaccount.support.reply', $t->id), ['body' => 'One more thing'])->assertRedirect();
        $this->assertSame('ticket_reply', $csr->adminNotifications()->value('kind'));
        $this->assertSame($before + 2, $csr->adminNotifications()->count());

        // The number, the latest 5 in the menu, and Settings (with Switch User) instead of switching in the menu
        $this->actingAs($csr, 'web');
        $unread = $csr->adminNotifications()->whereNull('read_at')->count();
        $this->getJson(route('admin.notifications'))->assertJsonPath('count', $unread);
        $page = $this->get(route('ranger.dashboard'))->assertSee('All Notifications')->assertSee(route('admin.settings'))->assertDontSee('data-test-switch', false);
        $page->assertSee($t->name.' wrote back on '.$t->number(), false);
        $this->get(route('admin.settings'))->assertOk()->assertSee('Support ticket chats')->assertSee('Switch User');
        $this->get(route('admin.notifications.index'))->assertOk()->assertSee('Notify me');

        // Opening one marks it read and goes to it
        $n = $csr->adminNotifications()->whereNull('read_at')->firstOrFail();
        $this->get(route('admin.notifications.open', $n))->assertRedirect($n->url);
        $this->assertNotNull($n->fresh()->read_at);
        $this->post(route('admin.notifications.read-all'))->assertRedirect();
        $this->assertSame(0, $csr->adminNotifications()->whereNull('read_at')->count());

        // Turned off: no more ticket chats
        $this->put(route('admin.settings.save'), ['kinds' => ['ticket_new']])->assertRedirect();
        $this->assertFalse($csr->fresh()->wantsNotification('ticket_reply'));
        $this->actingAs($c, 'customer')->post(route('myaccount.support.reply', $t->id), ['body' => 'And another'])->assertRedirect();
        $this->assertSame(0, AdminNotification::where('user_id', $csr->id)->where('kind', 'ticket_reply')->whereNull('read_at')->count());
        // Someone else's notification can't be opened
        $other = AdminNotification::where('user_id', '!=', $csr->id)->firstOrFail();
        $this->actingAs($csr, 'web')->get(route('admin.notifications.open', $other))->assertNotFound();
    }

    public function test_lando_on_the_site_now_and_demo_export_all(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail(), 'web')
            ->get(route('lando.dashboard'))->assertOk()->assertSee('On the Site Now')->assertSee('Pages Today')->assertSee('Came From');
        $out = storage_path('framework/testing/demo-all-'.uniqid());
        $this->artisan('demo:export', ['--all' => true, '--limit' => 8, '--out' => $out])->assertSuccessful();
        $this->assertFileExists($out.'/admin/index.html');
        File::deleteDirectory($out);
    }
}
