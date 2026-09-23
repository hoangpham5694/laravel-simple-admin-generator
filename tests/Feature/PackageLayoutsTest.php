<?php

namespace HoangPhamDev\SimpleAdminGenerator\Tests\Feature;

use HoangPhamDev\SimpleAdminGenerator\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\View;

class PackageLayoutsTest extends TestCase
{
    protected function tearDown(): void
    {
        app(Filesystem::class)->deleteDirectory(resource_path('views/vendor/sag/layouts'));

        parent::tearDown();
    }

    public function testLayoutsAreLoadedFromThePackageAndCanBePublished(): void
    {
        $this->assertTrue(View::exists('sag::layouts.app'));
        $this->assertTrue(View::exists('sag::layouts.navbar'));
        $this->assertTrue(View::exists('sag::layouts.sidebar'));

        $this->artisan('vendor:publish', ['--tag' => 'sag-layouts'])
            ->assertExitCode(0);

        $this->assertFileExists(resource_path('views/vendor/sag/layouts/app.blade.php'));
        $this->assertFileExists(resource_path('views/vendor/sag/layouts/navbar.blade.php'));
        $this->assertFileExists(resource_path('views/vendor/sag/layouts/sidebar.blade.php'));
    }

    public function testSidebarComposerProvidesMenuItemsForPackageAndApplicationViews(): void
    {
        $applicationSidebar = resource_path('views/sag/layouts/sidebar.blade.php');
        app(Filesystem::class)->ensureDirectoryExists(dirname($applicationSidebar));
        app(Filesystem::class)->put($applicationSidebar, '{{ $sagMenuItems->count() }}');

        $packageView = View::make('sag::layouts.sidebar');
        $applicationView = View::make('sag.layouts.sidebar');

        $packageView->render();
        $applicationView->render();

        $this->assertArrayHasKey('sagMenuItems', $packageView->getData());
        $this->assertArrayHasKey('sagMenuItems', $applicationView->getData());

        app(Filesystem::class)->deleteDirectory(resource_path('views/sag/layouts'));
    }
}
