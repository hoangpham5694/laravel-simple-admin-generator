<?php

namespace HoangPhamDev\SimpleAdminGenerator\Services;

use HoangPhamDev\SimpleAdminGenerator\Enums\AdminMenuLinkType;
use HoangPhamDev\SimpleAdminGenerator\Models\AdminMenuItem;
use Illuminate\Database\Connection;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminMenuJsonService
{
    public function __construct(private readonly Filesystem $files)
    {
    }

    public function syncAfterCommit(Connection $connection): void
    {
        if ($connection->transactionLevel() > 0) {
            $connection->afterCommit(fn () => $this->sync($connection->getName()));

            return;
        }

        $this->sync($connection->getName());
    }

    public function sync(?string $connectionName = null): void
    {
        $path = $this->path();
        $items = AdminMenuItem::on($connectionName)->ordered()->get();
        $json = json_encode(
            $items->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->replace($path, $json . PHP_EOL);
    }

    public function syncFromJson(?string $connectionName = null): int
    {
        $json = $this->files->get($this->path());
        if (!is_array(json_decode($json, false, 512, JSON_THROW_ON_ERROR))) {
            throw ValidationException::withMessages(['items' => 'The menu JSON must be an array.']);
        }

        $items = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        Validator::make(['items' => $items], [
            'items' => ['present', 'array'],
            'items.*' => ['required', 'array:id,parent_id,key,title,icon,sort_order,link_type,target,parameters,permission,target_window,is_active,created_at,updated_at'],
            'items.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.parent_id' => ['nullable', 'integer', 'min:1'],
            'items.*.key' => ['required', 'string', 'max:255', 'distinct'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.icon' => ['nullable', 'string', 'max:255'],
            'items.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'items.*.link_type' => ['sometimes', Rule::in(AdminMenuLinkType::values())],
            'items.*.target' => ['nullable', 'string', 'max:255'],
            'items.*.parameters' => ['nullable', 'array'],
            'items.*.permission' => ['nullable', 'string', 'max:255'],
            'items.*.target_window' => ['sometimes', 'string', 'max:10'],
            'items.*.is_active' => ['sometimes', 'boolean'],
            'items.*.created_at' => ['nullable', 'date'],
            'items.*.updated_at' => ['nullable', 'date'],
        ])->validate();

        $parents = [];
        foreach ($items as $item) {
            $parents[(int) $item['id']] = isset($item['parent_id']) ? (int) $item['parent_id'] : null;
        }
        foreach ($parents as $id => $parentId) {
            $visited = [$id => true];
            while ($parentId !== null) {
                if (!array_key_exists($parentId, $parents) || isset($visited[$parentId])) {
                    throw ValidationException::withMessages([
                        'items' => 'Menu parents must exist in the JSON and must not form a cycle.',
                    ]);
                }
                $visited[$parentId] = true;
                $parentId = $parents[$parentId];
            }
        }

        $model = (new AdminMenuItem())->setConnection($connectionName);
        $connection = $model->getConnection();

        return $connection->transaction(function () use ($items, $parents, $model, $connection): int {
            $table = $connection->table($model->getTable());
            // DELETE is transactional; TRUNCATE would implicitly commit on MySQL.
            $table->delete();

            foreach ($items as $item) {
                $record = $model->newInstance();
                $record->forceFill(array_replace([
                    'icon' => null,
                    'sort_order' => 0,
                    'link_type' => AdminMenuLinkType::None->value,
                    'target' => null,
                    'parameters' => null,
                    'permission' => null,
                    'target_window' => '_self',
                    'is_active' => true,
                ], $item, ['parent_id' => null]));
                $record->setCreatedAt($item['created_at'] ?? $record->freshTimestamp());
                $record->setUpdatedAt($item['updated_at'] ?? $record->freshTimestamp());
                // Query builder writes keep model events from overwriting the source JSON.
                $table->insert($record->getAttributes());
            }

            // Link parents after all IDs exist, regardless of JSON record order.
            foreach ($parents as $id => $parentId) {
                if ($parentId !== null) {
                    $connection->table($model->getTable())->where('id', $id)->update([
                        'parent_id' => $parentId,
                    ]);
                }
            }

            return count($items);
        });
    }

    private function path(): string
    {
        return config('sag.menu_json_path') ?? resource_path('admin-menu-items.json');
    }
}
