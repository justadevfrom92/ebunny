<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EmailPart;
use App\Models\EmailTemplate;
use App\Models\ReportUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Email headers and footers (edit them; each template picks one of each) and every Walker file's own page. */
class EmailPartsAndFilePagesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
    }

    public function test_templates_use_their_header_and_footer(): void
    {
        $this->get(route('rodeo.parts'))->assertOk()->assertSee('Standard Footer')->assertSee('Billing Header');
        $c = Customer::firstOrFail();
        $t = EmailTemplate::whereNull('email_footer_id')->whereNull('email_header_id')->firstOrFail();
        // No picks: the defaults wrap the body
        $this->assertStringContainsString('Go to My Account', $t->render($c));
        $this->assertStringNotContainsString('Go to My Account', $t->renderBody($c));

        // Edit the default footer: every template using it changes
        $footer = EmailPart::where('kind', 'footer')->where('is_default', true)->firstOrFail();
        $this->put(route('rodeo.parts.update', $footer), ['name' => $footer->name, 'html' => '<p>New footer for {{first_name}}</p>'])->assertRedirect();
        $this->assertStringContainsString('New footer for '.e($c->first_name), $t->fresh()->render($c));

        // A template picks its own header and footer
        $header = EmailPart::where('kind', 'header')->where('name', 'Rewards Header')->firstOrFail();
        $legal = EmailPart::where('kind', 'footer')->where('name', 'Legal Only')->firstOrFail();
        $this->get(route('rodeo.templates.edit', $t))->assertOk()->assertSee('Rewards Header')->assertSee('data-part-html', false);
        $this->put(route('rodeo.templates.update', $t), ['name' => $t->name, 'subject' => $t->subject, 'body' => $t->body, 'email_header_id' => $header->id, 'email_footer_id' => $legal->id])->assertRedirect();
        $html = $t->fresh()->render($c);
        $this->assertStringContainsString('Rangler Rewards', $html);
        $this->assertStringContainsString('PUCT Cert', $html);
        // A footer can't be used as a header
        $this->put(route('rodeo.templates.update', $t), ['name' => $t->name, 'subject' => $t->subject, 'body' => $t->body, 'email_header_id' => $legal->id])->assertSessionHasErrors('email_header_id');

        // New footer, made the default; deleting a non-default sends its templates back to the default
        $this->post(route('rodeo.parts.store'), ['kind' => 'footer', 'name' => 'Holiday Footer', 'html' => '<p>Happy holidays</p>', 'is_default' => 1])->assertRedirect();
        $holiday = EmailPart::where('name', 'Holiday Footer')->firstOrFail();
        $this->assertTrue($holiday->is_default);
        $this->assertFalse($footer->fresh()->is_default);
        $this->delete(route('rodeo.parts.destroy', $legal))->assertRedirect(route('rodeo.parts'));
        $this->assertNull($t->fresh()->email_footer_id);
        $this->assertStringContainsString('Happy holidays', $t->fresh()->render($c));
        $this->delete(route('rodeo.parts.destroy', $holiday))->assertStatus(422);   // the default stays
    }

    public function test_every_walker_file_has_its_own_page(): void
    {
        Storage::fake('local');
        $this->post(route('walker.uploads.store'), ['title' => 'Vendor rates', 'category' => 'Vendor', 'file' => UploadedFile::fake()->createWithContent('rates.csv', "Region,Rate\nNorth,12.5\nSouth,11.9\n")])
            ->assertRedirect();
        $u = ReportUpload::where('title', 'Vendor rates')->firstOrFail();
        // The list links to it; the page previews the CSV as a table
        $this->get(route('walker.folder', 'vendor'))->assertSee(route('walker.uploads.show', $u), false);
        $this->get(route('walker.uploads.show', $u))->assertOk()->assertSee('<th>Region</th>', false)->assertSee('11.9')->assertSee(route('walker.uploads.download', $u), false);
        $this->get(route('walker.uploads.download', $u))->assertOk()->assertDownload('rates.csv');

        // Edit details and upload a new version
        $this->patch(route('walker.uploads.update', $u), ['title' => 'Vendor rates (Oct)', 'category' => 'Finance', 'description' => 'Updated',
            'file' => UploadedFile::fake()->createWithContent('rates-oct.csv', "Region,Rate\nNorth,13.1\n")])->assertRedirect();
        $this->get(route('walker.uploads.show', $u))->assertSee('Vendor rates (Oct)')->assertSee('13.1')->assertDontSee('11.9');

        // A text file shows as text; a PDF shows inline
        $txt = ReportUpload::where('original_name', 'like', '%.txt')->firstOrFail();
        Storage::disk('local')->put($txt->path, 'Dear {{customer.first_name}}');
        $this->get(route('walker.uploads.show', $txt))->assertOk()->assertSee('file-text', false);
        $this->post(route('walker.uploads.store'), ['title' => 'Signed contract', 'category' => 'Legal Documents', 'file' => UploadedFile::fake()->create('contract.pdf', 20, 'application/pdf')])->assertRedirect();
        $pdf = ReportUpload::where('title', 'Signed contract')->firstOrFail();
        $this->get(route('walker.uploads.show', $pdf))->assertOk()->assertSee(route('walker.uploads.view', $pdf), false);
        $this->get(route('walker.uploads.view', $pdf))->assertOk()->assertHeader('content-disposition', 'inline; filename="contract.pdf"');
    }
}
