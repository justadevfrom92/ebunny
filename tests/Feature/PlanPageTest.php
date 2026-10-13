<?php

namespace Tests\Feature;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every active plan has its own page; plan cards and price grids link to it. */
class PlanPageTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_each_active_plan_has_a_page(): void
    {
        foreach (Plan::where('active', true)->get() as $plan) {
            $this->get('/plans/'.$plan->pageSlug())->assertOk()->assertSee($plan->name)->assertSee('Price by utility area')
                ->assertSee('Plan details')->assertSee('/checkout?plan='.urlencode($plan->internal), false);
        }
        $plan = Plan::where('active', true)->where('term', '>', 1)->firstOrFail();
        $this->get('/plans/'.$plan->pageSlug().'?zip=75201')->assertOk()->assertSee('Your area')->assertSee('zip=75201', false);
        $this->get('/plans/nope')->assertNotFound();
        $inactive = Plan::where('active', false)->first();
        if ($inactive) {
            $this->get('/plans/'.$inactive->pageSlug())->assertNotFound();
        }
        // Versions of a plan that share a name each get their own page
        $this->get('/plans/energy-texas-monthly-etm2')->assertOk()->assertSee('/checkout?plan=ETM2', false);
        $this->get('/plans/energy-texas-monthly')->assertOk()->assertSee('/checkout?plan=ETM"', false);
        // The website's plan cards link here
        $this->assertStringContainsString("'plans/' + encodeURIComponent(p.page || p.slug)", file_get_contents(public_path('frontend/js/main.js')));
    }
}
