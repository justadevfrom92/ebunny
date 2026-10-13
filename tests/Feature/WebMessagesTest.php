<?php

namespace Tests\Feature;

use App\Models\ContactLog;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Corral → Web Messages: sample messages, a page per message with the matching account, reply and handled. */
class WebMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_messages_list_page_reply_and_handled(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
        $this->assertGreaterThan(10, ContactMessage::count());
        $open = ContactMessage::open()->whereNotNull('customer_id')->firstOrFail();
        $this->get(route('corral.messages.index'))->assertOk()->assertSee('Open')->assertSee('Recently Handled')->assertSee(route('corral.messages.show', $open), false);
        $this->get(route('corral.messages.index', ['topic' => 'billing']))->assertOk()->assertDontSee('Is there an outage');
        $this->get(route('corral.messages.show', $open))->assertOk()->assertSee($open->customer->account)->assertSee('Send Reply')->assertSee('Mark Handled');

        $this->post(route('corral.messages.reply', $open), ['reply' => 'Hi, all set.', 'handled' => 1])->assertRedirect(route('corral.messages.show', $open));
        $open->refresh();
        $this->assertSame('Hi, all set.', $open->reply);
        $this->assertNotNull($open->handled_at);
        $this->assertTrue(ContactLog::where('customer_id', $open->customer_id)->where('template', 'Web Message Reply')->exists());
        $this->post(route('corral.messages.handled', $open))->assertRedirect();
        $this->assertNull($open->fresh()->handled_at);   // Reopen
        $this->get(route('corral.messages.show', $open))->assertSee('Reply sent')->assertSee('Reopen', false);
    }
}
