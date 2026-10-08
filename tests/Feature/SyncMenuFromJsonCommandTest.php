<?php

namespace HoangPhamDev\SimpleAdminGenerator\Tests\Feature;

use HoangPhamDev\SimpleAdminGenerator\Services\AdminMenuKeyService;
use HoangPhamDev\SimpleAdminGenerator\Tests\TestCase;

class SyncMenuFromJsonCommandTest extends TestCase
{
    public function testRegisteredCommandImportsConfiguredJsonIntoSelectedConnection(): void
    {
        app(AdminMenuKeyService::class)->create(['title' => 'Old menu']);
        file_put_contents(config('sag.menu_json_path'), '[{"id":42,"key":"new","title":"New menu"}]');

        $this->artisan('sag:sync-menu-from-json', ['--connection' => 'testing'])
            ->expectsOutput('Admin menu records replaced from JSON: 1 items imported.')
            ->assertSuccessful();

        $this->assertDatabaseCount('admin_menu_items', 1);
        $this->assertDatabaseHas('admin_menu_items', ['id' => 42, 'key' => 'new']);
        $this->assertDatabaseMissing('admin_menu_items', ['key' => 'old_menu']);
    }

    public function testCommandImportsAnEmptyArray(): void
    {
        app(AdminMenuKeyService::class)->create(['title' => 'Old menu']);
        file_put_contents(config('sag.menu_json_path'), '[]');

        $this->artisan('sag:sync-menu-from-json')
            ->expectsOutput('Admin menu records replaced from JSON: 0 items imported.')
            ->assertSuccessful();

        $this->assertDatabaseCount('admin_menu_items', 0);
    }

    public function testCommandFailsForMissingFileWithoutChangingDatabase(): void
    {
        app(AdminMenuKeyService::class)->create(['title' => 'Old menu']);
        config(['sag.menu_json_path' => $this->menuJsonDirectory . '/missing.json']);

        $this->artisan('sag:sync-menu-from-json')->assertFailed();

        $this->assertDatabaseHas('admin_menu_items', ['key' => 'old_menu']);
    }

    public function testCommandFailsForMalformedJsonWithoutChangingDatabase(): void
    {
        app(AdminMenuKeyService::class)->create(['title' => 'Old menu']);
        file_put_contents(config('sag.menu_json_path'), '{broken');

        $this->artisan('sag:sync-menu-from-json')->assertFailed();

        $this->assertDatabaseHas('admin_menu_items', ['key' => 'old_menu']);
    }

    public function testCommandDisplaysValidationErrorsWithoutChangingDatabase(): void
    {
        app(AdminMenuKeyService::class)->create(['title' => 'Old menu']);
        file_put_contents(config('sag.menu_json_path'), '{}');

        $this->artisan('sag:sync-menu-from-json')
            ->expectsOutput('The menu JSON must be an array.')
            ->assertFailed();

        $this->assertDatabaseHas('admin_menu_items', ['key' => 'old_menu']);
    }
}
