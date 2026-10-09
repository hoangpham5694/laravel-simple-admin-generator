<?php

namespace HoangPhamDev\SimpleAdminGenerator\Data;

use InvalidArgumentException;

final class SearchResult
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $url,
    ) {
        // Accept local absolute paths and HTTP(S) URLs only. Reject browser
        // normalization tricks (control characters, backslashes, protocol-relative URLs).
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url)
            || !(preg_match('~^/(?!/)~', $url)
                || (preg_match('~^https?://~i', $url) && filter_var($url, FILTER_VALIDATE_URL)))) {
            throw new InvalidArgumentException('Search result URL must be a local path or an HTTP(S) URL.');
        }
    }
}
