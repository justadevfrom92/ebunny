<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PdfGeneration;
use App\Models\PdfTemplate;
use App\Models\User;
use App\Support\PdfTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Rodeo → PDFs: templates like email templates, previewed for an account, made into PDFs that are kept. */
class PdfTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_create_preview_and_make_a_pdf(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
        $this->get(route('rodeo.pdfs'))->assertOk()->assertSee('Payment Reminder Letter');
        $this->get(route('rodeo.pdfs.create'))->assertOk()->assertSee('Fill-Ins');

        $this->post(route('rodeo.pdfs.store'), ['name' => 'Move-In Checklist', 'category' => 'Service', 'title' => 'Moving In, {{first_name}}?', 'status' => 'Active', 'show_address' => '1',
            'body' => "# Before you move\n- Pick a start date\n| Account | {{account}} |\n---\n**Thanks!**\nPlain words with {{balance}}.", 'footer' => '{{brand}}'])->assertRedirect();
        $t = PdfTemplate::where('name', 'Move-In Checklist')->firstOrFail();
        $c = Customer::orderBy('id')->firstOrFail();
        $this->get(route('rodeo.pdfs.edit', $t))->assertOk()->assertSee('Moving In, '.$c->first_name.'?')->assertSee('data-pdf64=', false)->assertSee('<svg', false)->assertSee($c->account);

        $this->post(route('rodeo.pdfs.generate', $t), ['account' => 'nope'])->assertSessionHasErrors('account');
        $this->post(route('rodeo.pdfs.generate', $t), ['account' => $c->account])->assertRedirect();
        $g = PdfGeneration::latest('id')->firstOrFail();
        $this->assertSame($c->id, $g->customer_id);
        $this->get(route('rodeo.pdfs.generated'))->assertOk()->assertSee('#'.$g->id);
        $this->get(route('rodeo.pdfs.generated.show', $g))->assertOk()->assertSee('PDF #'.$g->id);
        $pdf = $this->get(route('rodeo.pdfs.generated.pdf', [$g, 'download' => 1]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());
        $this->assertTrue($c->notes()->where('body', 'like', '%Move-In Checklist%')->exists());
    }

    public function test_long_templates_run_onto_more_pages_with_page_numbers(): void
    {
        $doc = ['title' => 'Long', 'body' => str_repeat("A paragraph of words that goes on for a while to fill the page.\n\n", 80), 'footer' => 'x', 'show_address' => false];
        $pdf = PdfTemplateRenderer::build($doc, null);
        $this->assertGreaterThan(1, $pdf->pageCount());
        $out = $pdf->output('Long');
        $this->assertStringContainsString('/Count '.$pdf->pageCount(), $out);
        $this->assertStringContainsString('Page 2 of '.$pdf->pageCount(), $pdf->svg(1));
    }
}
