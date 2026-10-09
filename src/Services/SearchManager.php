<?php

namespace HoangPhamDev\SimpleAdminGenerator\Services;

use HoangPhamDev\SimpleAdminGenerator\Contracts\SearchProvider;
use HoangPhamDev\SimpleAdminGenerator\Data\SearchResult;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use LogicException;

class SearchManager
{
    public function __construct(private Container $container) {}

    public static function enabled(): bool
    {
        $providers = config('sag.search.providers', []);
        return config('sag.search.enabled', false) === true && is_array($providers) && $providers !== [];
    }

    /** @return array<int, array{result: SearchResult, providerLabel: string, providerClass: string}> */
    public function search(string $keyword, Authenticatable $admin, int $limit): array
    {
        $results = [];
        if ($limit < 1) return $results;
        foreach (config('sag.search.providers', []) as $class) {
            if (!is_string($class) || !is_a($class, SearchProvider::class, true)) {
                throw new LogicException('Configured search provider must implement SearchProvider.');
            }
            $provider = $this->container->make($class);
            $label = trim($provider->label());
            if ($label === '') throw new LogicException("Search provider [{$class}] must declare a non-empty label.");
            $count = 0;
            foreach ($provider->search($keyword, $admin, $limit) as $result) {
                if (!$result instanceof SearchResult) throw new LogicException("Search provider [{$class}] must return SearchResult objects.");
                $results[] = ['result' => $result, 'providerLabel' => $label, 'providerClass' => $class];
                if (++$count >= $limit) break;
            }
        }
        return $results;
    }
}
