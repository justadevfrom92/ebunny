<?php

namespace Tests\Feature;

use App\Models\Market;
use App\Models\Plan;
use App\Models\Rate;
use App\Models\RateImport;
use App\Models\User;
use App\Services\Catalog;
use App\Support\Xlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Astro → Update Rates → Upload Rates: read in the background, nothing live until confirmed. */
class RateImportTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_csv_upload_waits_for_confirm_then_goes_live(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
        $this->get(route('astro.rates.edit'))->assertOk()->assertSee('Upload Rates')->assertSee('id="rate-upload"', false);
        $plan = Plan::where('active', true)->firstOrFail();
        $market = Market::firstOrFail();
        $before = Rate::count();
        $csv = "Plan,Market,Rate\n{$plan->internal},{$market->short},19.123\nNOPE,{$market->short},12\n{$plan->internal},{$market->name},abc\n";
        $this->post(route('astro.rates.imports.store'), ['file' => UploadedFile::fake()->createWithContent('rates.csv', $csv)])->assertRedirect();
        $import = RateImport::latest('id')->firstOrFail();
        // The queue runs right away in tests; nothing is live yet
        $this->assertSame('ready', $import->status);
        $this->assertCount(1, $import->changes);
        $this->assertCount(2, $import->problems);
        $this->assertSame($before, Rate::count());
        $this->get(route('astro.rates.imports.show', $import))->assertOk()->assertSee('Confirm Rates')->assertSee('19.123')->assertSee('No plan "NOPE"');

        $this->post(route('astro.rates.imports.confirm', $import), ['effective_on' => today()->toDateString()])->assertRedirect();
        $this->assertSame('applied', $import->fresh()->status);
        $this->assertEqualsWithDelta(19.123, (float) app(Catalog::class)->latestRates()[$plan->id.'-'.$market->id]->energy, 0.0001);
        $this->post(route('astro.rates.imports.discard', $import))->assertStatus(422);   // already live
    }

    public function test_excel_grid_upload_and_bad_files(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
        $plan = Plan::where('active', true)->firstOrFail();
        $markets = Market::orderBy('name')->get();
        $xlsx = Xlsx::build(['Plan', ...$markets->pluck('short')->all()], [[$plan->internal, ...$markets->map(fn ($m, $i) => 20 + $i / 10)->all()]]);
        $this->post(route('astro.rates.imports.store'), ['file' => UploadedFile::fake()->createWithContent('grid.xlsx', $xlsx)])->assertRedirect();
        $import = RateImport::latest('id')->firstOrFail();
        $this->assertSame('ready', $import->status, (string) $import->error);
        $this->assertSame($markets->count(), $import->rows);

        $this->post(route('astro.rates.imports.store'), ['file' => UploadedFile::fake()->createWithContent('x.csv', "a,b\n1,2\n")]);
        $this->assertSame('failed', RateImport::latest('id')->first()->status);
        $this->post(route('astro.rates.imports.store'), ['file' => UploadedFile::fake()->create('x.pdf', 10)])->assertSessionHasErrors('file');
        $this->get(route('astro.rates.imports.template'))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
