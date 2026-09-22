<?php

namespace HoangPhamDev\SimpleAdminGenerator\Tests\Feature;

use HoangPhamDev\SimpleAdminGenerator\PackageServiceProvider;
use Illuminate\Support\Facades\Blade;
use Orchestra\Testbench\TestCase;

class FormComponentsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PackageServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('s', 32)));
        $app['config']->set('session.driver', 'array');
    }

    public function testInputRendersLabelAttributesAndOldValue(): void
    {
        $this->flashOldInput(['profile.email' => 'old@example.test']);

        $html = Blade::render('<x-sag-form.input name="profile.email" label="Email" type="email" value="new@example.test" placeholder="name@example.test" data-field="email" class="custom" />');

        $this->assertStringContainsString('for="profile_email"', $html);
        $this->assertStringContainsString('name="profile.email"', $html);
        $this->assertStringContainsString('value="old@example.test"', $html);
        $this->assertStringContainsString('placeholder="name@example.test"', $html);
        $this->assertStringContainsString('data-field="email"', $html);
        $this->assertStringContainsString('form-control', $html);
        $this->assertStringContainsString('custom', $html);
    }

    public function testSelectAndMultiSelectNormalizeOldValues(): void
    {
        $this->flashOldInput(['status' => 'published', 'category_ids' => ['2']]);

        $html = Blade::render('<x-sag-form.select name="status" :options="[\'draft\' => \'Draft\', \'published\' => \'Published\']" selected="draft" placeholder="Choose" /><x-sag-form.multi-select name="category_ids" :options="[1 => \'One\', 2 => \'Two\']" :selected="[1]" />');

        $this->assertStringContainsString('<option value="">Choose</option>', $html);
        $this->assertStringContainsString('<option value="published" selected>Published</option>', $html);
        $this->assertStringContainsString('name="category_ids[]"', $html);
        $this->assertStringContainsString('<option value="2" selected>Two</option>', $html);
    }

    public function testDatetimeLoadsAssetsOnceAndFileNeverRendersValue(): void
    {
        $html = Blade::render('<x-sag-form.datetime name="starts_at" value="2026-08-26 14:30" /><x-sag-form.datetime name="ends_at" enable-seconds /><x-sag-form.file name="document" value="forbidden" accept="application/pdf" multiple />@stack(\'style\')@stack(\'script\')');

        $this->assertStringContainsString('value="2026-08-26 14:30"', $html);
        $this->assertStringContainsString('data-date-format="Y-m-d H:i:S"', $html);
        $this->assertStringContainsString('type="file"', $html);
        $this->assertStringContainsString('accept="application/pdf"', $html);
        $this->assertStringNotContainsString('value="forbidden"', $html);
        $this->assertSame(1, substr_count($html, 'flatpickr.min.css'));
        $this->assertSame(1, substr_count($html, 'flatpickr.min.js'));
        $this->assertSame(1, substr_count($html, 'sag-form.js'));
    }

    public function testGeneratorFormStubUsesComponents(): void
    {
        $stub = file_get_contents(__DIR__.'/../../stubs/Generator/views/ui/form.blade.php');

        $this->assertSame(3, substr_count($stub, '<x-sag-form.input'));
    }

    private function flashOldInput(array $input): void
    {
        $session = app('session')->driver();
        $session->start();
        $session->flashInput($input);
        app('request')->setLaravelSession($session);
    }
}
