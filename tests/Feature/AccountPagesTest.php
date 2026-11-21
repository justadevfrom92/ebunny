<?php

namespace Tests\Feature;

use App\Models\HistoryItem;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserSignIn;
use App\Support\AdminMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** The Account area: My Profile is the dashboard (details, access, sign-ins, activity); Change Password. */
class AccountPagesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function csr(): User
    {
        return User::where('email', 'csr@example.com')->firstOrFail();
    }

    public function test_my_profile_is_the_account_dashboard_with_access_and_activity(): void
    {
        $this->actingAs($this->csr());
        $this->get('/admin/my/profile')->assertOk()->assertSeeInOrder(['Your Details', 'Work Details', 'My Activity', 'Signed In On', 'What You Can Open'])
            ->assertDontSee('Sign-in History')->assertSee('<table data-page="0">', false)
            ->assertDontSee('<h4>You</h4>', false)->assertDontSee('stat-row', false);
        $this->get('/admin/my/password')->assertOk()->assertSee('Change Password')->assertSee('My Profile');
        // CSRs aren't administrators: What You Can Open is read-only
        $this->get('/admin/my/profile')->assertDontSee(route('admin.account.access.edit', 'corral'), false);
        $this->get(route('admin.account.access.edit', 'corral'))->assertForbidden();
        $this->get('/admin/my/access')->assertNotFound();
        $this->get('/admin/my/activity')->assertNotFound();
        // The Account app opens on My Profile
        $this->assertSame(route('admin.account.profile'), AdminMenu::appInfo('account')['home']);
    }

    public function test_profile_saves_name_and_extension_only(): void
    {
        $u = $this->csr();
        $this->actingAs($u)->put('/admin/my/profile', ['name' => 'Casey Renamed', 'phone_ext' => '4455', 'title' => 'Boss'])->assertRedirect('/admin/my/profile');
        $u->refresh();
        $this->assertSame('Casey Renamed', $u->name);
        $this->assertSame('4455', $u->phone_ext);
        $this->assertNotSame('Boss', $u->title);
    }

    public function test_password_change_needs_the_current_password(): void
    {
        $u = $this->csr();
        $this->actingAs($u)->put('/admin/my/password', ['current_password' => 'wrong', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertSessionHasErrors('current_password');
        $this->put('/admin/my/password', ['current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertSessionHasNoErrors()->assertRedirect('/admin/my/password');
        $this->assertTrue(Hash::check('newpass123', $u->refresh()->password));
    }

    public function test_cannot_change_password_while_logged_in_as_someone(): void
    {
        $this->actingAs($this->csr())->withSession(['impersonator_id' => 1])
            ->put('/admin/my/password', ['current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertForbidden();
    }

    public function test_sign_out_other_devices_removes_other_sessions(): void
    {
        config(['session.driver' => 'database']);
        $u = $this->csr();
        DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $u->id, 'ip_address' => '10.0.0.9', 'user_agent' => 'Mozilla/5.0 (iPhone) Safari/604', 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($u)->get('/admin/my/profile')->assertOk()->assertSee('Safari on iOS');
        $this->post('/admin/my/access/sign-out-others')->assertRedirect(route('admin.account.profile').'#signed-in');
        $this->assertFalse(DB::table('sessions')->where('id', 'other-device')->exists());
    }

    public function test_activity_lists_only_your_own_entries(): void
    {
        $u = $this->csr();
        $mine = HistoryItem::create(['user_id' => $u->id, 'model' => 'Test_model', 'group' => 'Notes', 'action' => 'created', 'summary' => 'My own test note', 'created_at' => now()]);
        $theirs = HistoryItem::create(['user_id' => 1, 'model' => 'Test_model', 'group' => 'Notes', 'action' => 'created', 'summary' => 'Someone else note', 'created_at' => now()]);
        $this->actingAs($u)->get('/admin/my/profile?q=note')->assertOk()->assertSee('My own test note')->assertDontSee('Someone else note');
        $this->get('/admin/my/activity/'.$mine->id)->assertOk()->assertSee('My own test note');
        $this->get('/admin/my/activity/'.$theirs->id)->assertNotFound();
    }

    /** A sign-in for $u from another device, still signed in; returns its record (its #ID is used in links). */
    private function otherDevice(User $u, string $ip = '10.0.0.9', string $ua = 'Mozilla/5.0 (iPhone) Safari/604', array $data = []): UserSignIn
    {
        DB::table('sessions')->insert(['id' => $id = 'dev-'.md5($ip.$ua), 'user_id' => $u->id, 'ip_address' => $ip, 'user_agent' => $ua,
            'payload' => base64_encode(json_encode($data + ['signed_in_at' => now()->subHour()->timestamp])), 'last_activity' => time()]);

        return UserSignIn::create(['user_id' => $u->id, 'impersonator_id' => $data['impersonator_id'] ?? null, 'session_key' => hash('sha256', $id), 'ip' => $ip, 'user_agent' => $ua,
            'how' => isset($data['impersonator_id']) ? 'log_in_as' : 'password', 'signed_in_at' => now()->subHour()]);
    }

    public function test_each_sign_in_opens_its_own_page_and_can_be_signed_out(): void
    {
        config(['session.driver' => 'database']);
        $u = $this->csr();
        $key = $this->otherDevice($u);
        $this->actingAs($u)->get('/admin/my/profile')->assertSee(route('admin.account.device', $key), false);
        $this->get(route('admin.account.device', $key))->assertOk()->assertSee('Safari on iOS')->assertSee('10.0.0.9')->assertSee(now()->subHour()->format('n/j/Y g:i A'))
            ->assertSee('Secure Your Account')->assertSee('recognize this device')->assertSee('recognize this IP Address')
            ->assertSee('<div class="btn disabled" aria-disabled="true">Block</div>', false)->assertDontSee('Report to Administrators');
        $this->post(route('admin.account.device.sign-out', $key))->assertRedirect(route('admin.account.profile').'#signed-in');
        $this->assertFalse(DB::table('sessions')->where('id', 'dev-'.md5('10.0.0.9Mozilla/5.0 (iPhone) Safari/604'))->exists());
        // It stays in the history, ended
        $this->assertSame('Signed out from another device', $key->fresh()->ended_reason);
        $this->get(route('admin.account.device', $key))->assertOk()->assertSee('Signed out from another device')->assertSee('This sign-in has already ended');
        $this->post(route('admin.account.device.sign-out', $key))->assertNotFound();
    }

    public function test_block_keeps_that_device_out_and_report_tells_the_administrators(): void
    {
        config(['session.driver' => 'database']);
        $u = $this->csr();
        $this->actingAs($u)->post(route('admin.account.device.block', $this->otherDevice($u)))->assertRedirect();
        $this->assertDatabaseHas('user_blocks', ['user_id' => $u->id, 'ip' => '10.0.0.9', 'reported_at' => null]);
        $this->get('/admin/my/profile')->assertSee('Blocked Devices')->assertSee('Unblock');

        // That device can't sign in, and is signed out if it was still signed in
        auth()->logout();
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9', 'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone) Safari/604'])
            ->post('/admin/login', ['email' => $u->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($u)->withServerVariables(['REMOTE_ADDR' => '10.0.0.9', 'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone) Safari/604'])
            ->get('/admin/my/profile')->assertRedirect(route('admin.login'));
        $this->assertGuest();

        // Report: blocked and the Sheriff users are told
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'Symfony']);
        $admin = User::where('email', 'admin@example.com')->first();
        $before = $admin->adminNotifications()->where('kind', 'security')->count();
        $this->actingAs($u)->post(route('admin.account.device.report', $this->otherDevice($u, '10.0.0.77', 'Mozilla/5.0 (Windows) Firefox/120')), ['reason' => 'Not me'])->assertRedirect();
        $this->assertDatabaseHas('user_blocks', ['user_id' => $u->id, 'ip' => '10.0.0.77', 'reason' => 'Not me']);
        $this->assertSame($before + 1, $admin->adminNotifications()->where('kind', 'security')->count());

        // Unblock
        $block = UserBlock::where('ip', '10.0.0.9')->first();
        $this->delete(route('admin.account.unblock', $block))->assertRedirect();
        $this->assertModelMissing($block);
    }

    public function test_someone_using_log_in_as_shows_and_can_be_ended(): void
    {
        config(['session.driver' => 'database']);
        $u = $this->csr();
        $admin = User::where('email', 'admin@example.com')->first();
        $key = $this->otherDevice($u, '10.0.0.5', 'Mozilla/5.0 (Macintosh; Mac OS X) Chrome/120', ['impersonator_id' => $admin->id]);
        $this->actingAs($u)->get('/admin/my/profile')->assertSee($admin->name.' using Log in as');
        $this->get(route('admin.account.device', $key))->assertSee('is using Log in as')->assertSee('End Log in as');
        $this->post(route('admin.account.device.sign-out', $key))->assertSessionHas('status', $admin->name.' is no longer logged in as you.');
    }

    public function test_cannot_block_your_own_browser(): void
    {
        config(['session.driver' => 'database']);
        $u = $this->csr();
        $key = $this->otherDevice($u, '127.0.0.1', 'Symfony');
        $this->actingAs($u)->post(route('admin.account.device.block', $key))->assertStatus(422);
    }

    public function test_signed_in_on_is_the_whole_history_current_first_ten_a_page(): void
    {
        config(['session.driver' => 'database']);
        $u = $this->csr();
        $old = UserSignIn::where('user_id', $u->id)->count();
        $this->assertGreaterThan(10, $old);   // sample history
        $live = $this->otherDevice($u, '10.0.0.40', 'Mozilla/5.0 (Windows) Firefox/120');
        $page = $this->actingAs($u)->get('/admin/my/profile')->assertOk();
        $html = $page->getContent();
        preg_match('#id="signed-in".*?</table>#s', $html, $m);
        $this->assertSame(10, substr_count($m[0], 'class="click"'));
        $this->assertStringContainsString('#'.$live->id, $m[0]);   // signed in now: on the first page, at the top
        $this->assertLessThan(strpos($m[0], 'Expired') ?: PHP_INT_MAX, strpos($m[0], '#'.$live->id));
        $this->get('/admin/my/profile?signins_page=2')->assertOk()->assertSee('ID#');
    }

    public function test_signing_in_and_out_is_recorded(): void
    {
        $u = $this->csr();
        $before = UserSignIn::where('user_id', $u->id)->count();
        $this->post('/admin/login', ['email' => $u->email, 'password' => 'password']);
        $s = UserSignIn::where('user_id', $u->id)->latest('id')->first();
        $this->assertSame([$before + 1, null, 'password'], [UserSignIn::where('user_id', $u->id)->count(), $s->ended_at, $s->how]);
        $this->post('/admin/logout');
        $this->assertSame('Signed out', $s->fresh()->ended_reason);
    }

    public function test_admins_can_edit_their_own_access_from_what_you_can_open(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $this->actingAs($admin)->get('/admin/my/profile')->assertSee(route('admin.account.access.edit', 'corral'), false);
        $this->get(route('admin.account.access.edit', 'corral'))->assertOk()->assertSee('Always allow')->assertSee('Never allow');
        $this->put(route('admin.account.access.save', 'corral'), ['access' => 'deny', 'access_note' => 'Testing'])->assertRedirect(route('admin.account.profile').'#access');
        $this->assertFalse($admin->fresh()->hasPerm('corral'));
        $this->put(route('admin.account.access.save', 'corral'), ['access' => 'role'])->assertRedirect();
        $this->assertTrue($admin->fresh()->hasPerm('corral'));
        // Can't lock yourself out of Sheriff
        $this->put(route('admin.account.access.save', 'sheriff'), ['access' => 'deny'])->assertSessionHasErrors('access');
        $this->assertTrue($admin->fresh()->hasPerm('sheriff'));
        $this->get(route('admin.account.access.edit', 'nope'))->assertNotFound();
    }
}
