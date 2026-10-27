<?php

namespace Tests\Feature;

use App\Models\ApiLog;
use App\Models\HistoryItem;
use App\Models\Phonecall;
use App\Models\StarEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tables lead with the record's ID as the link, and every ID opens a page inside the same app. */
class IdFirstTablesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_no_backend_table_starts_with_a_date_column(): void
    {
        $dateFirst = '#<thead><tr><th[^>]*>(When|Date|Time|Started|Sent|Failed|Requested|Received|Paid|Billed|Called|Run|Entered|Month|Date Ordered|Date Saved)</th>#';
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views/backend'))) as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.blade.php')) {
                $this->assertDoesNotMatchRegularExpression($dateFirst, file_get_contents($f->getPathname()), $f->getPathname().' has a table whose first column is a date');
            }
        }
    }

    public function test_record_pages_open_in_each_app(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
        $star = StarEntry::firstOrFail();
        $this->get(route('bounty.records.show', ['star_entry', $star->id]))->assertOk()->assertSee('Star Entry #'.$star->id)->assertSee($star->reason);
        $this->get(route('corral.api-calls.show', ApiLog::whereNotNull('customer_id')->firstOrFail()))->assertOk()->assertSee('What Was Sent')->assertSee('What Came Back')->assertSee('•••• hidden');
        $this->get(route('sheriff.api-calls.show', ApiLog::firstOrFail()))->assertOk()->assertSee('What Came Back');
        $this->get(route('corral.records.show', ['phonecall', Phonecall::firstOrFail()->id]))->assertOk()->assertSee('Phone Call #');
        $this->get(route('corral.records.show', ['nope', 1]))->assertNotFound();
        $this->get(route('corral.api-calls.show', 999999))->assertNotFound();
        // Passwords and secrets never show
        $this->get(route('sheriff.records.show', ['history_item', HistoryItem::firstOrFail()->id]))->assertOk()->assertSee('History Entry #');
        // The account's lists lead with IDs
        $c = $star->customer;
        $this->get(route('corral.customers.show', $c))->assertOk()->assertSee(route('corral.records.show', ['star_entry', $star->id]), false);
        $this->get(route('bounty.redemptions'))->assertOk();
    }
}
