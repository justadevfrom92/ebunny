<?php

namespace Tests\Feature;

use App\Models\HistoryItem;
use App\Models\User;
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
        $this->get('/admin/my/profile')->assertOk()->assertSeeInOrder(['Your Details', 'Work Details', 'My Activity', 'Signed In On', 'What You Can Open', 'Sign-in History'])
            ->assertDontSee('<h4>You</h4>', false)->assertDontSee('stat-row', false);
        $this->get('/admin/my/password')->assertOk()->assertSee('Change Password')->assertSee('My Profile');
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
}
