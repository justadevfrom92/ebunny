<?php

namespace Tests\Feature;

use App\Jobs\HeartbeatPing;
use App\Models\AdminNotification;
use App\Models\Heartbeat;
use App\Models\HeartbeatAction;
use App\Models\JobRun;
use App\Models\User;
use App\Support\Health;
use App\Support\HeartbeatControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** The heartbeat: the every-minute check, the queue ping, Sheriff → Heartbeat, and telling admins when something goes bad. */
class HeartbeatTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_the_command_beats_pings_the_queue_and_keeps_a_check(): void
    {
        Queue::fake();
        $before = Heartbeat::count();
        $this->artisan('et:heartbeat')->assertSuccessful();
        $this->assertSame($before + 1, Heartbeat::count());
        $this->assertTrue(Health::lastPulse('scheduler')->gt(now()->subMinute()));
        Queue::assertPushed(HeartbeatPing::class);
        (new HeartbeatPing(now()->subSeconds(5)->toIso8601String()))->handle();
        $this->assertSame(['ok', 'ok'], [Health::checks()['scheduler'][0], Health::checks()['queue_worker'][0]]);
    }

    public function test_a_stopped_queue_worker_is_bad_and_admins_hear_about_it(): void
    {
        DB::table('heartbeat_pulses')->where('key', 'queue')->update(['beat_at' => now()->subMinutes(30)]);
        Heartbeat::query()->update(['results' => json_encode(array_map(fn () => ['ok', 'Fine'], Health::CHECKS)), 'status' => 'ok']);
        $this->assertSame('bad', Health::checks()['queue_worker'][0]);
        Health::run();
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertTrue(AdminNotification::where('user_id', $admin->id)->where('kind', 'health')->where('title', 'like', '%Queue worker%')->exists());

        // Sheriff → Heartbeat shows it; Check Now runs the checks
        $this->actingAs($admin, 'web');
        $this->get(route('sheriff.heartbeat'))->assertOk()->assertSee('Heartbeat')->assertSee('Queue worker')->assertSee('Last 24 Hours')->assertSee('Latest Actions')->assertSee('Problems');
        $this->post(route('sheriff.heartbeat.check'))->assertRedirect();
        // Not for people without Sheriff
        $this->actingAs(User::where('email', 'csr@example.com')->firstOrFail(), 'web')->get(route('sheriff.heartbeat'))->assertRedirect();
    }

    public function test_every_check_has_its_own_page_with_controls(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail(), 'web');
        $this->get(route('sheriff.heartbeat.checks'))->assertOk()->assertSee(route('sheriff.heartbeat.show', 'queue_worker'), false);
        foreach (Health::CHECKS as $key => [$name]) {
            $page = $this->get(route('sheriff.heartbeat.show', $key))->assertOk()->assertSee($name)->assertSee('Controls')->assertSee('Run Check Now')->assertSee('Changes')->assertSee('Control Log');
            foreach (HeartbeatControl::actions($key) as $act => [$label]) {
                if ($act !== 'up') {   // Bring Up shows only once it's down
                    $page->assertSee(route('sheriff.heartbeat.control', [$key, $act]), false);
                }
            }
        }
        $this->get(route('sheriff.heartbeat.show', 'nope'))->assertNotFound();
        $this->post(route('sheriff.heartbeat.control', ['cache', 'down']))->assertNotFound();   // the cache can't be taken down
        $this->get(route('sheriff.heartbeat.log'))->assertOk()->assertSee('Send Due Now');
    }

    public function test_take_down_and_bring_up(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'web');

        // Campaigns: held while down
        $this->post(route('sheriff.heartbeat.control', ['campaigns', 'down']), ['reason' => 'Pricing review'])->assertRedirect(route('sheriff.heartbeat.show', 'campaigns'));
        $this->assertTrue(HeartbeatControl::isDown('campaigns'));
        $this->assertSame('down', Health::checks()['campaigns'][0]);
        $this->assertStringContainsString('Pricing review', Health::checks()['campaigns'][1]);
        $this->artisan('et:campaigns')->assertSuccessful();
        $this->assertStringContainsString('taken down', (string) JobRun::where('command', 'et:campaigns')->latest('id')->value('message'));
        $this->get(route('sheriff.heartbeat.show', 'campaigns'))->assertSee('Bring Up')->assertSee('Taken Down');
        $this->post(route('sheriff.heartbeat.control', ['campaigns', 'up']))->assertRedirect();
        $this->assertFalse(HeartbeatControl::isDown('campaigns'));
        $this->assertSame(2, HeartbeatAction::where('check_key', 'campaigns')->where('user_id', $admin->id)->where('created_at', '>', now()->subMinute())->count());

        // Website: visitors get the maintenance page, staff and My Account still get through
        $this->post(route('sheriff.heartbeat.control', ['website', 'down']))->assertRedirect();
        $this->get('/shop')->assertOk();
        auth('web')->logout();
        $this->get('/shop')->assertStatus(503)->assertSee('maintenance');
        $this->getJson('/site/session?path=/index.html')->assertJsonPath('blocked.message', HeartbeatControl::MAINTENANCE);
        $this->get('/myaccount/login')->assertOk();
        $this->actingAs($admin, 'web')->post(route('sheriff.heartbeat.control', ['website', 'up']));
        auth('web')->logout();
        $this->get('/shop')->assertOk();
    }

    public function test_restart_and_clear_controls(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail(), 'web');
        $this->assertGreaterThan(0, DB::table('failed_jobs')->count());
        $this->post(route('sheriff.heartbeat.control', ['failed_jobs', 'flush']))->assertRedirect()->assertSessionHas('status');
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->post(route('sheriff.heartbeat.control', ['queue_worker', 'restart']))->assertSessionHas('status');
        $this->post(route('sheriff.heartbeat.control', ['database', 'reconnect']))->assertSessionHas('status');
        $this->post(route('sheriff.heartbeat.control', ['cache', 'clear']))->assertSessionHas('status');
        $this->post(route('sheriff.heartbeat.control', ['crons', 'reset']))->assertSessionHas('status');
        $this->assertSame(5, HeartbeatAction::where('created_at', '>', now()->subMinute())->count());
        // Only Sheriff users
        $this->actingAs(User::where('email', 'csr@example.com')->firstOrFail(), 'web')->post(route('sheriff.heartbeat.control', ['cache', 'clear']))->assertRedirect();
        $this->assertSame(5, HeartbeatAction::where('created_at', '>', now()->subMinute())->count());
    }
}
