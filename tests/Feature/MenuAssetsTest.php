<?php

namespace HoangPhamDev\SimpleAdminGenerator\Tests\Feature;

use HoangPhamDev\SimpleAdminGenerator\PackageServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase;

class MenuAssetsTest extends TestCase
{
    private string $assetDirectory;

    protected function getPackageProviders($app): array
    {
        return [PackageServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $this->assetDirectory = sys_get_temp_dir() . '/sag-assets-' . bin2hex(random_bytes(8));
        $app->usePublicPath($this->assetDirectory);
    }

    public function testMenuPublishTagCopiesScriptsStylesAndThemeImages(): void
    {
        $directory = $this->assetDirectory;

        try {
            $this->artisan('vendor:publish', [
                '--provider' => PackageServiceProvider::class,
                '--tag' => 'sag-menu-assets',
                '--force' => true,
            ])->assertSuccessful();

            foreach (['jstree.min.js', 'themes/default/style.min.css', 'themes/default/32px.png', 'themes/default/40px.png', 'themes/default/throbber.gif'] as $file) {
                $source = __DIR__ . '/../../stubs/public/plugins/jstree/' . $file;
                $destination = $directory . '/sag/plugins/jstree/' . $file;
                $this->assertFileExists($destination);
                $this->assertSame(hash_file('sha256', $source), hash_file('sha256', $destination));
            }

            $paths = PackageServiceProvider::pathsToPublish(PackageServiceProvider::class, 'assets');
            $this->assertContains($directory . '/sag/plugins/jstree', $paths);
        } finally {
            (new Filesystem())->deleteDirectory($directory);
        }
    }
}
