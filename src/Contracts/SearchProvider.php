<?php

namespace HoangPhamDev\SimpleAdminGenerator\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use HoangPhamDev\SimpleAdminGenerator\Data\SearchResult;

interface SearchProvider
{
    public function label(): string;

    /** @return iterable<SearchResult> */
    public function search(string $keyword, Authenticatable $admin, int $limit): iterable;
}
