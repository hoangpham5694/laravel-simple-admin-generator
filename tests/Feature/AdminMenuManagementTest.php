<?php

namespace HoangPhamDev\SimpleAdminGenerator\Tests\Feature;

use HoangPhamDev\SimpleAdminGenerator\Database\Seeders\AdminMenuItemSeeder;
use HoangPhamDev\SimpleAdminGenerator\Enums\AdminMenuLinkType;
use HoangPhamDev\SimpleAdminGenerator\Http\Middleware\AuthAdmin;
use HoangPhamDev\SimpleAdminGenerator\Models\AdminMenuItem;
use HoangPhamDev\SimpleAdminGenerator\Services\AdminMenuKeyService;
use HoangPhamDev\SimpleAdminGenerator\Services\AdminMenuManager;
use HoangPhamDev\SimpleAdminGenerator\Tests\TestCase;
use Illuminate\Validation\ValidationException;

class AdminMenuManagementTest extends TestCase
{
    public function testDefaultMenuSeederIsIdempotent(): void
    {
        $this->seed(AdminMenuItemSeeder::class);
        $this->seed(AdminMenuItemSeeder::class);

        $this->assertDatabaseCount('admin_menu_items', 7);
        $this->assertDatabaseHas('admin_menu_items', [
            'key' => 'administrators.create',
            'target' => 'sag.admin.create',
        ]);
    }

    public function testSidebarTreeContainsResolvedActiveMenuItems(): void
    {
        $this->seed(AdminMenuItemSeeder::class);

        $tree = app(AdminMenuManager::class)->sidebarTree();
        $system = $tree->firstWhere('key', 'system');

        $this->assertCount(4, $tree);
        $this->assertNotNull($system);
        $this->assertCount(1, $system->childrenRecursive);
        $this->assertStringEndsWith('/admin/profile', $tree->firstWhere('key', 'profile')->resolved_url);
        $this->assertStringEndsWith(
            '/admin/menus',
            $system->childrenRecursive->firstWhere('key', 'system.menus')->resolved_url
        );
    }

    public function testCrudEndpointsGenerateHierarchicalKeys(): void
    {
        $this->withoutMiddleware(AuthAdmin::class);

        $parentResponse = $this->postJson(route('sag.menu.store'), [
            'title' => 'Content Management',
            'key' => '',
            'link_type' => AdminMenuLinkType::None->value,
            'target' => null,
            'parameters' => null,
            'target_window' => '_self',
            'is_active' => true,
        ])->assertCreated();

        $parentId = $parentResponse->json('item.id');
        $this->assertSame('content_management', $parentResponse->json('item.key'));

        $childResponse = $this->postJson(route('sag.menu.store'), [
            'parent_id' => $parentId,
            'title' => 'Articles',
            'key' => '',
            'link_type' => AdminMenuLinkType::Route->value,
            'target' => 'example.route',
            'parameters' => ['id' => 1],
            'target_window' => '_self',
            'is_active' => true,
        ])->assertCreated();

        $childId = $childResponse->json('item.id');
        $this->assertSame(
            'content_management.articles',
            $childResponse->json('item.key')
        );

        $this->putJson(route('sag.menu.update', $parentId), [
            'title' => 'Website Content',
            'key' => '',
            'link_type' => AdminMenuLinkType::None->value,
            'target' => null,
            'parameters' => null,
            'target_window' => '_self',
            'is_active' => true,
        ])->assertOk();

        $this->assertDatabaseHas('admin_menu_items', [
            'id' => $parentId,
            'key' => 'website_content',
        ]);
        $this->assertDatabaseHas('admin_menu_items', [
            'id' => $childId,
            'key' => 'website_content.articles',
        ]);

        $this->deleteJson(route('sag.menu.destroy', $parentId))->assertOk();
        $this->assertDatabaseHas('admin_menu_items', [
            'id' => $childId,
            'parent_id' => null,
            'key' => 'articles',
        ]);
    }

    public function testReorderRejectsParentChildCycles(): void
    {
        $parent = AdminMenuItem::query()->create([
            'key' => 'parent',
            'title' => 'Parent',
            'link_type' => AdminMenuLinkType::None,
        ]);
        $child = AdminMenuItem::query()->create([
            'parent_id' => $parent->id,
            'key' => 'parent.child',
            'title' => 'Child',
            'link_type' => AdminMenuLinkType::None,
        ]);

        $this->expectException(ValidationException::class);

        app(AdminMenuKeyService::class)->reorder([
            ['id' => $parent->id, 'parent_id' => $child->id, 'sort_order' => 10],
            ['id' => $child->id, 'parent_id' => $parent->id, 'sort_order' => 10],
        ]);
    }
}
