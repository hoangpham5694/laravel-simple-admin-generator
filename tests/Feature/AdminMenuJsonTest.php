<?php

namespace HoangPhamDev\SimpleAdminGenerator\Tests\Feature;

use HoangPhamDev\SimpleAdminGenerator\Database\Seeders\AdminMenuItemSeeder;
use HoangPhamDev\SimpleAdminGenerator\Models\AdminMenuItem;
use HoangPhamDev\SimpleAdminGenerator\Services\AdminMenuKeyService;
use HoangPhamDev\SimpleAdminGenerator\Services\AdminMenuJsonService;
use HoangPhamDev\SimpleAdminGenerator\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use JsonException;
use RuntimeException;

class AdminMenuJsonTest extends TestCase
{
    public function testModelChangesCreateAndReplaceJsonAtTheConfiguredPath(): void
    {
        $path = $this->menuJsonDirectory . '/nested/menus.json';
        config(['sag.menu_json_path' => $path]);
        $this->assertFileDoesNotExist($path);

        $item = AdminMenuItem::create([
            'key' => 'content',
            'title' => 'Nội dung',
            'parameters' => ['id' => 42],
            'is_active' => false,
        ]);

        $this->assertJsonMatchesDatabase();
        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Nội dung', $data[0]['title']);
        $this->assertSame(['id' => 42], $data[0]['parameters']);
        $this->assertFalse($data[0]['is_active']);

        $item->update(['title' => 'Updated']);
        $this->assertJsonMatchesDatabase();

        $item->delete();
        $this->assertJsonMatchesDatabase();
        $this->assertSame([], json_decode(file_get_contents($path), true));
    }

    public function testDefaultPathUsesTheHostApplicationsResourcesDirectory(): void
    {
        $this->app->setBasePath($this->menuJsonDirectory);
        config(['sag.menu_json_path' => null]);

        app(AdminMenuKeyService::class)->create(['title' => 'Dashboard']);

        $this->assertFileExists(resource_path('admin-menu-items.json'));
        $this->assertSame(
            AdminMenuItem::query()->ordered()->get()->toArray(),
            json_decode(file_get_contents(resource_path('admin-menu-items.json')), true)
        );
    }

    public function testHierarchicalUpdatesDeletesAndReorderingExportFinalKeys(): void
    {
        $service = app(AdminMenuKeyService::class);
        $parent = $service->create(['title' => 'Parent']);
        $child = $service->create(['title' => 'Child', 'parent_id' => $parent->id]);
        $grandchild = $service->create(['title' => 'Leaf', 'parent_id' => $child->id]);

        $parent = $service->update($parent, ['title' => 'Renamed']);
        $this->assertJsonMatchesDatabase();
        $this->assertSame('renamed.child.leaf', $grandchild->fresh()->key);

        $service->reorder([
            ['id' => $parent->id, 'parent_id' => $child->id, 'sort_order' => 20],
            ['id' => $child->id, 'parent_id' => null, 'sort_order' => 10],
            ['id' => $grandchild->id, 'parent_id' => $parent->id, 'sort_order' => 10],
        ]);
        $this->assertJsonMatchesDatabase();
        $this->assertSame('child.renamed.leaf', $grandchild->fresh()->key);
        $this->assertStringNotContainsString('__sag_tmp_', file_get_contents(config('sag.menu_json_path')));

        $service->delete($child->fresh());
        $this->assertJsonMatchesDatabase();
        $this->assertSame('renamed.leaf', $grandchild->fresh()->key);
    }

    public function testJsonIsOnlyUpdatedAfterTheOuterTransactionCommits(): void
    {
        $service = app(AdminMenuKeyService::class);
        $item = $service->create(['title' => 'Original']);
        $original = file_get_contents(config('sag.menu_json_path'));

        DB::beginTransaction();
        $service->update($item, ['title' => 'Rolled back']);
        $this->assertSame($original, file_get_contents(config('sag.menu_json_path')));
        DB::rollBack();
        $this->assertSame($original, file_get_contents(config('sag.menu_json_path')));

        DB::beginTransaction();
        $service->update($item->fresh(), ['title' => 'Committed']);
        $this->assertSame($original, file_get_contents(config('sag.menu_json_path')));
        DB::commit();
        $this->assertJsonMatchesDatabase();
        $this->assertNotSame($original, file_get_contents(config('sag.menu_json_path')));
    }

    public function testRollbackDoesNotCreateAFileAndSeederExportsAllItems(): void
    {
        DB::beginTransaction();
        app(AdminMenuKeyService::class)->create(['title' => 'Rolled back']);
        DB::rollBack();
        $this->assertFileDoesNotExist(config('sag.menu_json_path'));

        $this->seed(AdminMenuItemSeeder::class);
        $this->assertJsonMatchesDatabase();
        $this->assertCount(7, json_decode(file_get_contents(config('sag.menu_json_path')), true));
    }

    public function testImportReplacesAllRecordsAndPreservesIdsParentsAndSourceJson(): void
    {
        $service = app(AdminMenuKeyService::class);
        $parent = $service->create(['title' => 'Parent']);
        $child = $service->create([
            'title' => 'Nội dung',
            'parent_id' => $parent->id,
            'parameters' => ['id' => 42],
            'is_active' => false,
        ]);
        $records = AdminMenuItem::query()->ordered()->get()->toArray();
        $json = json_encode(array_reverse($records), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $oldItem = $service->create(['title' => 'Remove me']);
        file_put_contents(config('sag.menu_json_path'), $json);

        $this->assertSame(2, app(AdminMenuJsonService::class)->syncFromJson());
        $this->assertDatabaseMissing('admin_menu_items', ['id' => $oldItem->id]);
        $this->assertSame($records, AdminMenuItem::query()->ordered()->get()->toArray());
        $this->assertSame($parent->id, $child->fresh()->parent_id);
        $this->assertSame($json, file_get_contents(config('sag.menu_json_path')));
    }

    public function testImportAnEmptyArrayClearsTheMenuTable(): void
    {
        app(AdminMenuKeyService::class)->create(['title' => 'Original']);
        file_put_contents(config('sag.menu_json_path'), '[]');

        $this->assertSame(0, app(AdminMenuJsonService::class)->syncFromJson());
        $this->assertDatabaseCount('admin_menu_items', 0);
    }

    public function testInvalidJsonAndInvalidMenuTreesLeaveDatabaseUntouched(): void
    {
        $item = app(AdminMenuKeyService::class)->create(['title' => 'Original']);
        $invalidDocuments = [
            '{broken',
            '{}',
            '[{"id":1,"key":"a","title":"A","parent_id":99}]',
            '[{"id":1,"key":"a","title":"A","parent_id":2},{"id":2,"key":"b","title":"B","parent_id":1}]',
            '[{"id":1,"key":"a","title":"A"},{"id":1,"key":"b","title":"B"}]',
            '[{"id":1,"key":"a","title":"A","link_type":"invalid"}]',
        ];

        foreach ($invalidDocuments as $json) {
            file_put_contents(config('sag.menu_json_path'), $json);
            try {
                app(AdminMenuJsonService::class)->syncFromJson();
                $this->fail('Invalid menu JSON must be rejected.');
            } catch (JsonException | ValidationException $exception) {
                $this->assertDatabaseCount('admin_menu_items', 1);
                $this->assertSame('Original', $item->fresh()->title);
                $this->assertSame($json, file_get_contents(config('sag.menu_json_path')));
            }
        }
    }

    public function testMissingImportFileLeavesDatabaseUntouched(): void
    {
        $item = app(AdminMenuKeyService::class)->create(['title' => 'Original']);
        config(['sag.menu_json_path' => $this->menuJsonDirectory . '/missing.json']);

        try {
            app(AdminMenuJsonService::class)->syncFromJson();
            $this->fail('A missing file must be rejected.');
        } catch (FileNotFoundException $exception) {
            $this->assertDatabaseCount('admin_menu_items', 1);
            $this->assertSame('Original', $item->fresh()->title);
        }
    }

    public function testImportFailureRollsBackDeletionAndInsertedRecords(): void
    {
        $item = app(AdminMenuKeyService::class)->create(['title' => 'Original']);
        $json = '[{"id":1,"key":"new","title":"New"}]';
        file_put_contents(config('sag.menu_json_path'), $json);
        DB::listen(static function ($query): void {
            if (str_starts_with(strtolower($query->sql), 'insert into')) {
                throw new RuntimeException('Simulated import failure');
            }
        });

        try {
            app(AdminMenuJsonService::class)->syncFromJson();
            $this->fail('The simulated failure must interrupt the import.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated import failure', $exception->getMessage());
            $this->assertDatabaseCount('admin_menu_items', 1);
            $this->assertSame('Original', $item->fresh()->title);
            $this->assertSame($json, file_get_contents(config('sag.menu_json_path')));
        }
    }

    public function testImportUsesDefaultResourcesPathAndSupportsOuterRollback(): void
    {
        $item = app(AdminMenuKeyService::class)->create(['title' => 'Original']);
        $this->app->setBasePath($this->menuJsonDirectory);
        config(['sag.menu_json_path' => null]);
        mkdir(resource_path(), 0777, true);
        file_put_contents(resource_path('admin-menu-items.json'), '[{"id":1,"key":"new","title":"New"}]');

        DB::beginTransaction();
        $this->assertSame(1, app(AdminMenuJsonService::class)->syncFromJson());
        $this->assertDatabaseHas('admin_menu_items', ['key' => 'new']);
        DB::rollBack();
        $this->assertDatabaseCount('admin_menu_items', 1);
        $this->assertSame('Original', $item->fresh()->title);
    }

    private function assertJsonMatchesDatabase(): void
    {
        $this->assertSame(
            AdminMenuItem::query()->ordered()->get()->toArray(),
            json_decode(file_get_contents(config('sag.menu_json_path')), true, 512, JSON_THROW_ON_ERROR)
        );
    }
}
