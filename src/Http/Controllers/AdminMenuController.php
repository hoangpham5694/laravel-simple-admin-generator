<?php

namespace HoangPhamDev\SimpleAdminGenerator\Http\Controllers;

use App\Http\Controllers\Controller;
use HoangPhamDev\SimpleAdminGenerator\Enums\AdminMenuLinkType;
use HoangPhamDev\SimpleAdminGenerator\Http\Requests\AdminMenuItemRequest;
use HoangPhamDev\SimpleAdminGenerator\Http\Requests\ReorderAdminMenuItemsRequest;
use HoangPhamDev\SimpleAdminGenerator\Models\AdminMenuItem;
use HoangPhamDev\SimpleAdminGenerator\Services\AdminMenuKeyService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class AdminMenuController extends Controller
{
    public function __construct(private readonly AdminMenuKeyService $keyService)
    {
    }

    public function index(): Factory|View|Application
    {
        $meta = [
            'title' => 'Menu management',
            'breadcrumbs' => [
                [
                    'name' => 'Home',
                    'url' => route('sag.dashboard'),
                ],
                [
                    'name' => 'Menus',
                ],
            ],
        ];
        $linkTypes = AdminMenuLinkType::cases();

        return view('sag::menu.index', compact('meta', 'linkTypes'));
    }

    public function tree(): JsonResponse
    {
        $items = AdminMenuItem::query()->ordered()->get();

        return response()->json(
            $items->map(static function (AdminMenuItem $item): array {
                return [
                    'id' => (string) $item->id,
                    'parent' => $item->parent_id === null ? '#' : (string) $item->parent_id,
                    'text' => $item->title,
                    'icon' => $item->icon ?: 'far fa-circle',
                    'state' => [
                        'opened' => true,
                        'disabled' => false,
                    ],
                    'data' => [
                        'id' => $item->id,
                        'parent_id' => $item->parent_id,
                        'key' => $item->key,
                        'local_key' => $item->local_key,
                        'title' => $item->title,
                        'icon' => $item->icon,
                        'sort_order' => $item->sort_order,
                        'link_type' => $item->link_type->value,
                        'target' => $item->target,
                        'parameters' => $item->parameters,
                        'permission' => $item->permission,
                        'target_window' => $item->target_window,
                        'is_active' => $item->is_active,
                    ],
                ];
            })->values()
        );
    }

    public function store(AdminMenuItemRequest $request): JsonResponse
    {
        $menuItem = $this->keyService->create($request->validated());

        return response()->json([
            'message' => 'Menu item created successfully.',
            'item' => $menuItem,
        ], 201);
    }

    public function update(
        AdminMenuItemRequest $request,
        AdminMenuItem $adminMenuItem
    ): JsonResponse {
        $menuItem = $this->keyService->update(
            $adminMenuItem,
            $request->validated()
        );

        return response()->json([
            'message' => 'Menu item updated successfully.',
            'item' => $menuItem,
        ]);
    }

    public function destroy(AdminMenuItem $adminMenuItem): JsonResponse
    {
        $this->keyService->delete($adminMenuItem);

        return response()->json([
            'message' => 'Menu item deleted successfully.',
        ]);
    }

    public function reorder(ReorderAdminMenuItemsRequest $request): JsonResponse
    {
        $this->keyService->reorder($request->validated('items'));

        return response()->json([
            'message' => 'Menu order updated successfully.',
        ]);
    }
}
