<?php

namespace Tests\Feature;

use App\Jobs\HeartbeatPing;
use App\Models\AdminNotification;
use App\Models\Heartbeat;
use App\Models\User;
use App\Support\Health;
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
}
