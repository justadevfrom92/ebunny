<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Website files live in public/frontend, admin portal files in public/backend; addresses stay the same. */
class FrontendBackendFilesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_built_in_pages_keep_their_addresses_and_point_at_the_new_folders(): void
    {
        foreach (['/', '/index.html', '/plans.html', '/build-your-own-plan.html', '/business.html', '/contact-us.html'] as $url) {
            $html = $this->get($url)->assertOk()->streamedContent() ?: file_get_contents(public_path('frontend/'.(ltrim($url, '/') ?: 'index.html')));
            $this->assertStringContainsString('href="/frontend/css/style.css"', $html, $url);
            $this->assertStringContainsString('data-then="/frontend/js/main.js"', $html, $url);
        }
        $this->get('/nope.html')->assertNotFound();
        $this->get('/about-us')->assertOk()->assertSee('/frontend/css/style.css', false);
        $this->get('/admin/login')->assertOk()->assertSee('/backend/admin.css', false)->assertSee('/backend/admin.js', false);

        foreach (['frontend/css/style.css', 'frontend/js/main.js', 'backend/admin.css', 'backend/admin.js', 'shared/boot.js'] as $file) {
            $this->assertFileExists(public_path($file));
        }
        $this->assertFileDoesNotExist(public_path('admin-assets/admin.css'));
        $this->assertFileDoesNotExist(public_path('plans.html'));
    }
}
