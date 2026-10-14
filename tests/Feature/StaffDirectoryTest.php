<?php

namespace Tests\Feature;

use App\Models\HistoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sheriff → Staff Directory: profiles with permissions, and Log in as with a way back. */
class StaffDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    public function test_directory_and_profile(): void
    {
        $this->actingAs($this->admin());
        $csr = User::where('email', 'csr@example.com')->firstOrFail();
        $this->get(route('sheriff.users.index'))->assertOk()->assertSee('Staff Directory')->assertSee(route('sheriff.users.show', $csr), false);
        $this->get(route('sheriff.users.index', ['q' => 'Brightline']))->assertOk()->assertSee('Alex Overflow')->assertDontSee('"'.route('sheriff.users.show', $csr).'"', false);
        $this->get(route('sheriff.users.show', $csr))->assertOk()->assertSee('Admin Permissions')->assertSee('Taken away for this person')
            ->assertSee('Log in as CSR')->assertSee(route('sheriff.users.login-as', $csr), false)->assertSee(route('sheriff.users.edit', $csr), false);
        $ext = User::whereNotNull('company')->where('active', true)->firstOrFail();
        $this->get(route('sheriff.users.show', $ext))->assertOk()->assertSee($ext->company)->assertSee('Access Ends');
        // No Bunkhouse app any more
        $this->get('/admin/bunkhouse')->assertNotFound();
    }

    public function test_log_in_as_and_back(): void
    {
        $admin = $this->admin();
        $csr = User::where('email', 'csr@example.com')->firstOrFail();
        $this->actingAs($admin);

        $this->post(route('sheriff.users.login-as', $csr))->assertRedirect(route('admin.launcher'));
        $this->assertAuthenticatedAs($csr);
        $this->assertSame($admin->id, session('impersonator_id'));
        $this->get(route('admin.launcher'))->assertOk()->assertSee('logged in as <b>CSR Demo</b>', false)->assertSee('Back to Admin Demo');
        $this->get(route('sheriff.users.index'))->assertRedirect();   // CSR has no Sheriff
        $this->assertTrue(HistoryItem::where('record_id', $csr->id)->where('summary', 'like', 'Signed in by Admin Demo%')->exists());

        $this->post(route('admin.login-as.return'))->assertRedirect(route('sheriff.users.show', $csr));
        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));
        $this->post(route('admin.login-as.return'))->assertNotFound();
    }

    public function test_log_in_as_rules(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        // Not yourself, not a disabled person
        $this->post(route('sheriff.users.login-as', $admin))->assertSessionHasErrors('login_as');
        $this->post(route('sheriff.users.login-as', User::where('active', false)->firstOrFail()))->assertSessionHasErrors('login_as');
        $this->assertAuthenticatedAs($admin);

        // Someone with Sheriff but fewer rights can't log in as an administrator
        $it = User::where('email', 'like', 'sawyer.stackman@%')->firstOrFail();   // Administrator role without "configure"
        $this->actingAs($it)->post(route('sheriff.users.login-as', $admin))->assertSessionHasErrors('login_as');
        $this->assertAuthenticatedAs($it);
        $this->get(route('sheriff.users.show', $admin))->assertSee('They have access you don');
    }

    /** admin.js has one esc() helper; a second "var esc" in the same function hides it and breaks every confirm dialog. */
    public function test_admin_js_does_not_redeclare_esc(): void
    {
        $js = file_get_contents(public_path('backend/admin.js'));
        $this->assertSame(1, preg_match_all('/\b(function esc|var esc|let esc|const esc)\b/', $js));
    }
}
