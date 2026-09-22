<?php

namespace HoangPhamDev\SimpleAdminGenerator\Services;

use HoangPhamDev\SimpleAdminGenerator\Enums\AdminMenuLinkType;
use HoangPhamDev\SimpleAdminGenerator\Models\AdminMenuItem;
use Illuminate\Support\Facades\Route;

class MenuUrlResolver
{
    public function resolve(AdminMenuItem $item): string
    {
        return match ($item->link_type) {
            AdminMenuLinkType::None => '#',
            AdminMenuLinkType::Route => $this->resolveRoute($item),
            AdminMenuLinkType::RootPath => url('/' . ltrim((string) $item->target, '/')),
            AdminMenuLinkType::AdminPath => url(
                trim((string) config('sag.prefix', 'admin'), '/')
                . '/' . ltrim((string) $item->target, '/')
            ),
            AdminMenuLinkType::Url => (string) $item->target,
        };
    }

    public function isCurrent(AdminMenuItem $item): bool
    {
        return match ($item->link_type) {
            AdminMenuLinkType::Route => Route::currentRouteNamed((string) $item->target),
            AdminMenuLinkType::RootPath => $this->requestMatchesPath((string) $item->target),
            AdminMenuLinkType::AdminPath => $this->requestMatchesPath(
                trim((string) config('sag.prefix', 'admin'), '/')
                . '/' . ltrim((string) $item->target, '/')
            ),
            default => false,
        };
    }

    private function resolveRoute(AdminMenuItem $item): string
    {
        if (!Route::has((string) $item->target)) {
            return '#';
        }

        return route((string) $item->target, $item->parameters ?? []);
    }

    private function requestMatchesPath(string $path): bool
    {
        $path = trim($path, '/');

        if ($path === '') {
            return request()->path() === '/';
        }

        return request()->is($path, $path . '/*');
    }
}
