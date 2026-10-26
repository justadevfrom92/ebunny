<?php

namespace Tests\Feature;

use App\Models\ReportColumn;
use App\Models\ReportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Background reports: the user menu badge, the "report ready" feed, My Files, export columns and formats; Walker folders and ETL feeds. */
class FilesAndNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    public function test_user_menu_has_notifications_files_settings_and_logout(): void
    {
        $this->actingAs($this->admin());
        $this->get('/admin')->assertOk()->assertSee('data-user-menu', false)->assertSee('All Notifications')->assertSee('My Files')->assertSee('Settings')->assertDontSee('Switch user')->assertSee('Logout')
            ->assertSee(route('admin.notifications'), false);
        $this->get('/admin/corral')->assertSee('data-user-menu', false);
    }

    public function test_finished_report_shows_in_the_badge_until_seen(): void
    {
        Storage::fake('local');
        $user = User::where('email', 'csr@example.com')->firstOrFail();
        $this->actingAs($user);
        ReportRun::where('user_id', $user->id)->update(['seen_at' => now()]);
        $user->adminNotifications()->update(['read_at' => now()]);
        $this->getJson(route('admin.notifications'))->assertOk()->assertJson(['count' => 0]);

        $this->get('/admin/corral/reports/notes?run=1&start=2020-01-01&format=csv')->assertRedirect();
        $run = ReportRun::where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->assertSame('done', $run->status);
        $this->getJson(route('admin.notifications'))->assertOk()->assertJson(['count' => 1])
            ->assertJsonPath('items.0.id', $run->id)->assertJsonPath('items.0.download', route('admin.files.download', $run));
        $this->get('/admin/corral')->assertSee('data-files-badge', false)->assertSee('1 new');

        // Opening the file counts as seeing it
        $this->get(route('admin.files.show', $run))->assertOk()->assertSee(basename($run->file));
        $this->getJson(route('admin.notifications'))->assertJson(['count' => 0]);
        $this->get(route('admin.files'))->assertOk()->assertSee($run->label());

        // Someone else's file is off limits (unless they have Walker)
        $this->actingAs(User::where('email', 'marketing@example.com')->firstOrFail())->get(route('admin.files.show', $run))->assertNotFound();
    }

    public function test_export_columns_set_the_file_columns(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());
        $this->get('/admin/walker/reports/orders')->assertOk()->assertSee('Export Columns')->assertSee('As a key array');

        // Nested key => title array, flattened in running order
        $this->post(route('walker.reports.columns', 'orders'), ['key_array' => json_encode(['account' => 'Acct #', 'name' => 'Customer'])])->assertRedirect();
        $this->assertSame([['key' => 'account', 'title' => 'Acct #'], ['key' => 'name', 'title' => 'Customer']], ReportColumn::where('report', 'orders')->value('columns'));

        $this->post(route('walker.reports.run', 'orders'), ['format' => 'csv'])->assertRedirect();
        $run = ReportRun::where('report', 'orders')->latest('id')->firstOrFail();
        $this->assertMatchesRegularExpression('/^\W*"?Acct #"?,"?Customer/', Storage::disk('local')->get($run->file));

        // Rows: sorted by order, empty keys dropped
        $this->post(route('walker.reports.columns', 'orders'), ['cols' => [['key' => 'name', 'title' => 'Name', 'order' => 2], ['key' => 'account', 'title' => '', 'order' => 1], ['key' => '', 'title' => 'x']]])->assertRedirect();
        $this->assertSame([['key' => 'account', 'title' => 'account'], ['key' => 'name', 'title' => 'Name']], ReportColumn::where('report', 'orders')->value('columns'));

        $this->post(route('walker.reports.columns', 'orders'), ['key_array' => 'not json'])->assertSessionHasErrors('key_array');
        $this->post(route('walker.reports.columns', 'orders'), ['reset' => 1])->assertRedirect();
        $this->assertNull(ReportColumn::where('report', 'orders')->first());
    }

    public function test_walker_folders_and_etl_feeds(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('walker.folder', 'legal'))->assertOk()->assertSee('Terms of Service');
        $this->get(route('walker.folder', 'templates'))->assertOk()->assertSee('Monthly Payment Reminder - Spanish');
        $this->get('/admin/walker/files/nope')->assertNotFound();
        $this->get(route('walker.etl'))->assertOk()->assertSee('ERCOT 867_03 Usage Import')->assertSee(route('walker.etl.show', 'lockbox'), false);
        $this->get(route('walker.etl.show', ['ercot-867', 'status' => 'Failed']))->assertOk()->assertSee('Gateway timeout');
        $this->get(route('walker.etl.show', 'nope'))->assertNotFound();
    }

    public function test_run_page_shows_the_saved_file(): void
    {
        $this->actingAs($this->admin());
        $run = ReportRun::where('report', 'payments')->whereNotNull('file')->firstOrFail();
        $this->get(route('walker.runs.show', $run))->assertOk()->assertSee(basename($run->file))->assertDontSee('viewed on screen');
    }

    public function test_timeline_lists_everything_and_filters(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('sheriff.timeline'))->assertOk()->assertSee('Last 14 Days');
        foreach (['job' => 'Cron Job', 'report' => 'Report', 'user' => 'Person', 'automation' => 'Automation', 'api' => 'API'] as $k => $label) {
            $this->get(route('sheriff.timeline', ['kind' => [$k]]))->assertOk()->assertSee('>'.$label.'<', false);
        }
        // Backlog search for errors in one kind
        $this->get(route('sheriff.timeline', ['kind' => ['etl'], 'status' => 'bad', 'q' => 'Gateway timeout']))->assertOk()
            ->assertSee('ERCOT 867_03 Usage Import')->assertDontSee('>Person<', false);
        // One person's timeline, linked from their profile
        $u = User::where('email', 'csr@example.com')->firstOrFail();
        $this->get(route('sheriff.users.show', $u))->assertSee(route('sheriff.timeline', ['user' => $u->id]), false);
        $this->get(route('sheriff.timeline', ['user' => $u->id]))->assertOk()->assertSee($u->name.' — Timeline');
        $this->get(route('sheriff.timeline', ['status' => 'nope']))->assertSessionHasErrors('status');
    }

    public function test_apis_page_tests_each_connection(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('sheriff.integrations.index'))->assertOk()->assertSee('Test All Connections')->assertSee(route('sheriff.integrations.test', 'stripe'), false);
    }
}
