<?php

namespace HoangPhamDev\SimpleAdminGenerator\Database\Seeders;

use HoangPhamDev\SimpleAdminGenerator\Enums\AdminMenuLinkType;
use HoangPhamDev\SimpleAdminGenerator\Models\AdminMenuItem;
use Illuminate\Database\Seeder;

class AdminMenuItemSeeder extends Seeder
{
    public function run(): void
    {
        AdminMenuItem::query()->firstOrCreate(
            ['key' => 'dashboard'],
            [
                'title' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt',
                'sort_order' => 10,
                'link_type' => AdminMenuLinkType::Route,
                'target' => 'sag.dashboard',
                'target_window' => '_self',
                'is_active' => true,
            ]
        );

        AdminMenuItem::query()->firstOrCreate(
            ['key' => 'profile'],
            [
                'title' => 'Profile',
                'icon' => 'fas fa-user',
                'sort_order' => 20,
                'link_type' => AdminMenuLinkType::Route,
                'target' => 'sag.profile',
                'target_window' => '_self',
                'is_active' => true,
            ]
        );

        $administrators = AdminMenuItem::query()->firstOrCreate(
            ['key' => 'administrators'],
            [
                'title' => 'Administrators',
                'icon' => 'fas fa-users',
                'sort_order' => 30,
                'link_type' => AdminMenuLinkType::None,
                'target_window' => '_self',
                'is_active' => true,
            ]
        );

        AdminMenuItem::query()->firstOrCreate(
            ['key' => 'administrators.index'],
            [
                'parent_id' => $administrators->id,
                'title' => 'Index',
                'icon' => 'far fa-circle',
                'sort_order' => 10,
                'link_type' => AdminMenuLinkType::Route,
                'target' => 'sag.admin.index',
                'target_window' => '_self',
                'is_active' => true,
            ]
        );

        AdminMenuItem::query()->firstOrCreate(
            ['key' => 'administrators.create'],
            [
                'parent_id' => $administrators->id,
                'title' => 'Create',
                'icon' => 'far fa-circle',
                'sort_order' => 20,
                'link_type' => AdminMenuLinkType::Route,
                'target' => 'sag.admin.create',
                'target_window' => '_self',
                'is_active' => true,
            ]
        );

        $system = AdminMenuItem::query()->firstOrCreate(
            ['key' => 'system'],
            [
                'title' => 'System',
                'icon' => 'fas fa-cogs',
                'sort_order' => 40,
                'link_type' => AdminMenuLinkType::None,
                'target_window' => '_self',
                'is_active' => true,
            ]
        );

        AdminMenuItem::query()->firstOrCreate(
            ['key' => 'system.menus'],
            [
                'parent_id' => $system->id,
                'title' => 'Menus',
                'icon' => 'fas fa-sitemap',
                'sort_order' => 10,
                'link_type' => AdminMenuLinkType::Route,
                'target' => 'sag.menu.index',
                'target_window' => '_self',
                'is_active' => true,
            ]
        );
    }
}
