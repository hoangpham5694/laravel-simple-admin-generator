<?php

namespace HoangPhamDev\SimpleAdminGenerator\Services;

use HoangPhamDev\SimpleAdminGenerator\Models\AdminMenuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AdminMenuManager
{
    public function __construct(private readonly MenuUrlResolver $urlResolver)
    {
    }

    public function sidebarTree(): Collection
    {
        try {
            if (!Schema::hasTable('admin_menu_items')) {
                return collect();
            }

            $items = AdminMenuItem::query()
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->with('childrenRecursive')
                ->ordered()
                ->get();

            return $this->decorate($items);
        } catch (Throwable) {
            return collect();
        }
    }

    private function decorate(Collection $items): Collection
    {
        return $items
            ->filter(fn (AdminMenuItem $item): bool => $this->isVisible($item))
            ->map(function (AdminMenuItem $item): AdminMenuItem {
                $children = $this->decorate($item->childrenRecursive);
                $item->setRelation('childrenRecursive', $children);
                $item->setAttribute('resolved_url', $this->urlResolver->resolve($item));
                $item->setAttribute(
                    'menu_active',
                    $this->urlResolver->isCurrent($item)
                    || $children->contains(
                        fn (AdminMenuItem $child): bool => (bool) $child->menu_active
                    )
                );

                return $item;
            })
            ->values();
    }

    private function isVisible(AdminMenuItem $item): bool
    {
        return empty($item->permission) || Gate::allows($item->permission);
    }
}
