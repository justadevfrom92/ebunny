<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\StyleSheets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lando → Style Guide: every CSS variable, where it's used, and the components built from them. */
class StyleGuideTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_style_guide_lists_variables_and_components(): void
    {
        $vars = StyleSheets::variables();
        $this->assertSame('#102247', $vars['--navy']['value']);
        $this->assertSame('shared/css/tokens.css', $vars['--ok']['file']);       // status colors are shared by the website and admin
        $this->assertSame('#EDF4F7', $vars['--page']['resolved']);             // aliases resolve to the color they point at
        $this->assertArrayHasKey('frontend/css/style.css', $vars['--ok']['uses']);
        $this->assertContains('--cyan', StyleSheets::varsFor(['.btn'], 'backend/admin.css'));

        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
        $this->get(route('lando.styles'))->assertOk()
            ->assertSee('Style Guide')->assertSee('--navy')->assertSee('data-var="--cyan"', false)
            ->assertSee('Hard-coded Colors')->assertSee('My Account menu')->assertSee('public/shared/css/tokens.css');

        $this->actingAs(User::where('email', 'csr@example.com')->firstOrFail());
        $this->get(route('lando.styles'))->assertRedirect();
    }
}
