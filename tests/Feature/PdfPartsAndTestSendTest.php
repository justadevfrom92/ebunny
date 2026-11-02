<?php

namespace Tests\Feature;

use App\Mail\PdfTest;
use App\Models\PdfPart;
use App\Models\PdfTemplate;
use App\Models\PdfTestSend;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Rodeo → PDFs: headers & footers templates pick, and sending a test PDF by email. */
class PdfPartsAndTestSendTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_headers_and_footers_are_picked_by_templates(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
        $this->get(route('rodeo.pdf-parts'))->assertOk()->assertSee('Standard (navy)')->assertSee('Legal (PUCT)');
        $this->post(route('rodeo.pdf-parts.store'), ['kind' => 'footer', 'name' => 'Short', 'body' => 'Short footer zebra {{account}}'])->assertRedirect();
        $f = PdfPart::where('name', 'Short')->firstOrFail();
        $this->get(route('rodeo.pdf-parts.edit', $f))->assertOk()->assertSee('Preview');

        $t = PdfTemplate::orderBy('id')->firstOrFail();
        $this->put(route('rodeo.pdfs.update', $t), ['name' => $t->name, 'title' => $t->title, 'body' => $t->body, 'status' => 'Active', 'pdf_footer_id' => $f->id, 'footer' => ''])->assertRedirect();
        $this->assertStringStartsWith('Short footer zebra', $t->fresh()->document()['footer']);
        // Its own footer text wins over the footer picked
        $t->update(['footer' => 'Own footer']);
        $this->assertSame('Own footer', $t->fresh()->document()['footer']);
        $this->get(route('rodeo.pdfs.edit', $t))->assertOk()->assertSee('Send a Test PDF by Email');

        // Deleting a footer sends its templates back to the default
        $this->delete(route('rodeo.pdf-parts.destroy', $f))->assertRedirect();
        $this->assertNull($t->fresh()->pdf_footer_id);
    }

    public function test_send_a_test_pdf_by_email(): void
    {
        Mail::fake();
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
        $t = PdfTemplate::orderBy('id')->firstOrFail();
        $this->post(route('rodeo.pdfs.test', $t), ['to' => 'one@example.com, two@example.com'])->assertRedirect();
        Mail::assertSent(PdfTest::class, fn ($m) => $m->hasTo('one@example.com') && $m->hasTo('two@example.com') && str_starts_with($m->subjectLine, '[TEST]') && str_starts_with($m->pdf, '%PDF'));
        $this->assertDatabaseHas('pdf_test_sends', ['pdf_template_id' => $t->id, 'recipients' => 'one@example.com, two@example.com', 'status' => 'sent']);
        $this->post(route('rodeo.pdfs.test', $t), ['to' => 'not-an-email'])->assertSessionHasErrors('to');
        $this->assertSame(1, PdfTestSend::where('recipients', 'like', '%one@example.com%')->count());
    }
}
