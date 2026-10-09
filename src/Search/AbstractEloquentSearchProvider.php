<?php

namespace HoangPhamDev\SimpleAdminGenerator\Search;

use HoangPhamDev\SimpleAdminGenerator\Contracts\SearchProvider;
use Illuminate\Database\Eloquent\Model;

abstract class AbstractEloquentSearchProvider implements SearchProvider
{
    abstract protected function routeName(): string;

    /** @return array<string, mixed> */
    abstract protected function routeParameters(Model $record): array;

    protected function resultUrl(Model $record): string
    {
        return route($this->routeName(), $this->routeParameters($record));
    }
}
