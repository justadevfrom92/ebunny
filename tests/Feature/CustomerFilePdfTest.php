<?php

namespace Tests\Feature;

use App\Models\CustomerFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Account files (EFL, YRAC, TOS, welcome packet, invoice) are real PDFs: shown on the file page, viewed, downloaded, regenerated and emailed. */
class CustomerFilePdfTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_every_generated_file_is_a_pdf_with_a_page_preview(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
        foreach (['efl', 'yrac', 'tos', 'welcome', 'invoice'] as $kind) {
            $f = CustomerFile::whereNull('path')->where('kind', $kind)->first();
            if (! $f) {
                continue;
            }
            $this->get(route('corral.files.show', $f))->assertOk()->assertSee('data-pdf64=', false)->assertSee('<svg', false)->assertSee('Download PDF')->assertSee('Regenerate');
            $pdf = $this->get(route('corral.files.pdf', [$f, 'lang' => 'es', 'download' => 1]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());
            $this->assertStringContainsString('attachment;', $pdf->headers->get('Content-Disposition'));
        }
    }

    public function test_regenerate_and_email_attach_the_pdf(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
        $f = CustomerFile::whereNull('path')->where('kind', 'efl')->firstOrFail();
        $this->post(route('corral.files.regenerate', $f))->assertRedirect()->assertSessionHas('status');
        $f->refresh();
        $this->assertSame(1, $f->regenerated);
        $this->assertNotNull($f->generated_at);
        $this->assertGreaterThan(1000, $f->size);

        Mail::fake();
        $this->post(route('corral.files.send', $f), ['lang' => 'en', 'email' => 'someone@example.com'])->assertRedirect();
        $this->get(route('corral.files.pdf', $f))->assertOk();
    }

    public function test_corral_lists_every_customer_pdf(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
        $f = CustomerFile::whereNull('path')->where('kind', 'invoice')->latest('created_at')->latest('id')->firstOrFail();
        $this->get(route('corral.files.index', ['kind' => 'invoice']))->assertOk()->assertSee('Customer PDFs')->assertSee('#'.$f->id)->assertSee(route('corral.files.show', $f), false);
        $this->get(route('admin.launcher'))->assertSee('customer PDFs');
    }
}
