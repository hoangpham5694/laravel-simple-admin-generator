<?php

namespace HoangPhamDev\SimpleAdminGenerator\Services;

use HoangPhamDev\SimpleAdminGenerator\Models\AdminMenuItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminMenuKeyService
{
    public function create(array $attributes): AdminMenuItem
    {
        return DB::transaction(function () use ($attributes): AdminMenuItem {
            $parent = $this->findParent($attributes['parent_id'] ?? null);
            $localKey = $this->makeLocalKey(
                $attributes['key'] ?? null,
                $attributes['title'],
                $parent
            );
            $attributes['key'] = $this->makeFullKey($localKey, $parent);
            $attributes['parent_id'] = $parent?->id;
            $attributes['sort_order'] = $attributes['sort_order']
                ?? $this->nextSortOrder($parent?->id);

            $this->assertKeysAreAvailable([$attributes['key']]);

            return AdminMenuItem::query()->create($attributes);
        });
    }

    public function update(AdminMenuItem $menuItem, array $attributes): AdminMenuItem
    {
        return DB::transaction(function () use ($menuItem, $attributes): AdminMenuItem {
            $menuItem = AdminMenuItem::query()->lockForUpdate()->findOrFail($menuItem->id);
            $parent = $this->findParent($attributes['parent_id'] ?? null);
            $this->assertValidParent($menuItem, $parent);

            $oldKey = $menuItem->key;
            $localKey = $this->makeLocalKey(
                $attributes['key'] ?? null,
                $attributes['title'] ?? $menuItem->title,
                $parent
            );
            $newKey = $this->makeFullKey($localKey, $parent);

            $descendants = $this->descendantsOf($menuItem);

            $newKeys = [$menuItem->id => $newKey];
            foreach ($descendants as $descendant) {
                $suffix = Str::after($descendant->key, $oldKey . '.');
                $newKeys[$descendant->id] = $newKey . '.' . $suffix;
            }

            $this->assertKeysAreAvailable(array_values($newKeys), array_keys($newKeys));

            $attributes['parent_id'] = $parent?->id;
            $attributes['key'] = $newKey;
            if (($attributes['sort_order'] ?? null) === null) {
                unset($attributes['sort_order']);
            }
            $menuItem->fill($attributes)->save();

            foreach ($descendants as $descendant) {
                $descendant->update(['key' => $newKeys[$descendant->id]]);
            }

            return $menuItem->fresh(['parent']);
        });
    }

    public function delete(AdminMenuItem $menuItem): void
    {
        DB::transaction(function () use ($menuItem): void {
            $menuItem = AdminMenuItem::query()->lockForUpdate()->findOrFail($menuItem->id);
            $children = $menuItem->children()->lockForUpdate()->get();

            foreach ($children as $child) {
                $this->update($child, [
                    'parent_id' => null,
                    'key' => $child->local_key,
                ]);
            }

            $menuItem->delete();
        });
    }

    public function reorder(array $payload): void
    {
        DB::transaction(function () use ($payload): void {
            $items = AdminMenuItem::query()->lockForUpdate()->get()->keyBy('id');
            $submitted = collect($payload)->keyBy(
                static fn (array $item): int => (int) $item['id']
            );

            $databaseIds = $items->keys()->map(static fn ($id): int => (int) $id)->sort()->values();
            $submittedIds = $submitted->keys()->map(static fn ($id): int => (int) $id)->sort()->values();

            if ($databaseIds->all() !== $submittedIds->all()) {
                throw ValidationException::withMessages([
                    'items' => 'The complete menu tree must be submitted.',
                ]);
            }

            $tree = [];
            foreach ($submitted as $id => $item) {
                $parentId = empty($item['parent_id']) ? null : (int) $item['parent_id'];

                if ($parentId !== null && !$items->has($parentId)) {
                    throw ValidationException::withMessages([
                        'items' => "Parent menu item {$parentId} does not exist.",
                    ]);
                }

                $tree[(int) $id] = [
                    'parent_id' => $parentId,
                    'sort_order' => (int) $item['sort_order'],
                ];
            }

            $this->assertTreeHasNoCycles($tree);

            $newKeys = [];
            $buildKey = function (int $id) use (&$buildKey, &$newKeys, $tree, $items): string {
                if (isset($newKeys[$id])) {
                    return $newKeys[$id];
                }

                /** @var AdminMenuItem $item */
                $item = $items->get($id);
                $localKey = $item->local_key;
                $parentId = $tree[$id]['parent_id'];

                return $newKeys[$id] = $parentId === null
                    ? $localKey
                    : $buildKey($parentId) . '.' . $localKey;
            };

            foreach ($tree as $id => $item) {
                $buildKey($id);
            }

            $this->assertKeysAreAvailable(array_values($newKeys), array_keys($newKeys));

            foreach ($items as $item) {
                $item->update(['key' => '__sag_tmp_' . $item->id . '_' . Str::random(12)]);
            }

            foreach ($tree as $id => $item) {
                AdminMenuItem::query()->whereKey($id)->update([
                    'parent_id' => $item['parent_id'],
                    'sort_order' => $item['sort_order'],
                    'key' => $newKeys[$id],
                ]);
            }
        });
    }

    public function makeLocalKey(
        ?string $requestedKey,
        string $title,
        ?AdminMenuItem $parent = null
    ): string {
        $candidate = trim((string) $requestedKey);

        if ($parent !== null && Str::startsWith($candidate, $parent->key . '.')) {
            $candidate = Str::after($candidate, $parent->key . '.');
        }

        if (Str::contains($candidate, '.')) {
            $candidate = Str::afterLast($candidate, '.');
        }

        $localKey = Str::slug($candidate !== '' ? $candidate : $title, '_');

        if ($localKey === '') {
            throw ValidationException::withMessages([
                'key' => 'The menu key could not be generated.',
            ]);
        }

        return $localKey;
    }

    private function makeFullKey(string $localKey, ?AdminMenuItem $parent): string
    {
        return $parent === null ? $localKey : $parent->key . '.' . $localKey;
    }

    private function findParent(mixed $parentId): ?AdminMenuItem
    {
        if (empty($parentId)) {
            return null;
        }

        return AdminMenuItem::query()->lockForUpdate()->findOrFail((int) $parentId);
    }

    private function assertValidParent(
        AdminMenuItem $menuItem,
        ?AdminMenuItem $parent
    ): void {
        if ($parent === null) {
            return;
        }

        if (
            $parent->is($menuItem)
            || Str::startsWith($parent->key, $menuItem->key . '.')
        ) {
            throw ValidationException::withMessages([
                'parent_id' => 'A menu item cannot be moved below itself or one of its children.',
            ]);
        }
    }

    private function assertKeysAreAvailable(array $keys, array $ignoredIds = []): void
    {
        if (count($keys) !== count(array_unique($keys))) {
            throw ValidationException::withMessages([
                'key' => 'The resulting menu keys must be unique.',
            ]);
        }

        foreach ($keys as $key) {
            if (mb_strlen($key) > 255) {
                throw ValidationException::withMessages([
                    'key' => 'The resulting menu key may not be greater than 255 characters.',
                ]);
            }
        }

        $query = AdminMenuItem::query()->whereIn('key', $keys);
        if ($ignoredIds !== []) {
            $query->whereNotIn('id', $ignoredIds);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'key' => 'The resulting menu key has already been taken.',
            ]);
        }
    }

    private function assertTreeHasNoCycles(array $tree): void
    {
        $state = [];
        $visit = function (int $id) use (&$visit, &$state, $tree): void {
            if (($state[$id] ?? 0) === 1) {
                throw ValidationException::withMessages([
                    'items' => 'The menu tree contains a parent/child cycle.',
                ]);
            }

            if (($state[$id] ?? 0) === 2) {
                return;
            }

            $state[$id] = 1;
            $parentId = $tree[$id]['parent_id'];
            if ($parentId !== null) {
                $visit($parentId);
            }
            $state[$id] = 2;
        };

        foreach (array_keys($tree) as $id) {
            $visit((int) $id);
        }
    }

    private function nextSortOrder(?int $parentId): int
    {
        return ((int) AdminMenuItem::query()
            ->where('parent_id', $parentId)
            ->max('sort_order')) + 10;
    }

    private function descendantsOf(AdminMenuItem $menuItem): \Illuminate\Support\Collection
    {
        $descendants = collect();
        $parentIds = [$menuItem->id];

        while ($parentIds !== []) {
            $children = AdminMenuItem::query()
                ->whereIn('parent_id', $parentIds)
                ->lockForUpdate()
                ->get();

            if ($children->isEmpty()) {
                break;
            }

            $descendants = $descendants->concat($children);
            $parentIds = $children->pluck('id')->all();
        }

        return $descendants;
    }
}
