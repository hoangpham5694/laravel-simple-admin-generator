<?php

namespace HoangPhamDev\SimpleAdminGenerator\Models;

use HoangPhamDev\SimpleAdminGenerator\Enums\AdminMenuLinkType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AdminMenuItem extends Model
{
    protected $fillable = [
        'parent_id',
        'key',
        'title',
        'icon',
        'sort_order',
        'link_type',
        'target',
        'parameters',
        'permission',
        'target_window',
        'is_active',
    ];

    protected $casts = [
        'link_type' => AdminMenuLinkType::class,
        'parameters' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function activeChildren(): HasMany
    {
        return $this->children()->where('is_active', true);
    }

    public function childrenRecursive(): HasMany
    {
        return $this->activeChildren()->with('childrenRecursive');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function getLocalKeyAttribute(): string
    {
        return Str::afterLast($this->key, '.');
    }
}
