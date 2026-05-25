<?php

declare(strict_types=1);

namespace Markommerce\Config\Contracts;

use Closure;

interface ConfigCacheInterface
{
    /**
     * Returns the cached value if present (including nulls), else invokes loader and memoizes.
     *
     * @param Closure(): mixed $loader
     */
    public function get(
        string $cacheKey,
        Closure $loader,
    ): mixed;

    /**
     * Drops every entry whose stored key starts with `<configKey>|` or equals `<configKey>` exactly.
     */
    public function invalidatePrefix(string $configKey): void;

    /**
     * Drops everything (called by middleware between requests).
     */
    public function clear(): void;
}
