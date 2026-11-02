<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ranger: searching tickets (words, notes, names, numbers) and employee pages with the tickets each person handled. */
class RangerSearchAndEmployeesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_ticket_search_looks_through_numbers_text_notes_and_people(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin);
        $t = SupportTicket::whereNotNull('customer_id')->with('customer')->firstOrFail();
        $t->addNote('Unusual phrase zebra-umbrella for the search test', $admin);

        $this->get(route('ranger.tickets', ['q' => $t->number()]))->assertOk()->assertSee($t->number())->assertSee('1 ticket');
        $this->get(route('ranger.tickets', ['q' => 'zebra-umbrella']))->assertOk()->assertSee($t->number());
        $this->get(route('ranger.tickets', ['q' => 'zebra-umbrella', 'in' => ['text']]))->assertOk()->assertSee('0 tickets');
        $this->get(route('ranger.tickets', ['q' => $t->customer->account]))->assertOk()->assertSee($t->number());
        $digits = substr(preg_replace('/\D/', '', $t->customer->phone), -7);
        $this->get(route('ranger.tickets', ['q' => $digits]))->assertOk()->assertSee($t->number());
        $this->get(route('ranger.tickets', ['q' => 'nothing-matches-this']))->assertOk()->assertSee('0 tickets');
        $this->get(route('ranger.tickets', ['handled_by' => $admin->id, 'view' => 'all']))->assertOk()->assertSee($t->number());
    }

    public function test_employee_pages_list_the_tickets_each_person_handled(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $csr = User::where('email', 'csr@example.com')->firstOrFail();
        $this->actingAs($admin);
        $this->get(route('ranger.people'))->assertOk()->assertSee('Admin Demo')->assertSee('CSR Demo')->assertSee(route('ranger.people.show', $admin), false);

        $latest = fn (User $u) => SupportTicket::handledBy($u->id)->orderByRaw('coalesce(last_activity_at, created_at) desc')->first();
        $this->assertNotNull($latest($admin), 'the sample admin handled some tickets');
        $this->get(route('ranger.people.show', $admin))->assertOk()->assertSee('Tickets Handled')->assertSee($latest($admin)->number());
        $this->get(route('ranger.people.show', $csr))->assertOk()->assertSee($latest($csr)->number());
    }
}
