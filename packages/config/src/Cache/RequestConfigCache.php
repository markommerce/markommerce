<?php

declare(strict_types=1);

namespace Markommerce\Config\Cache;

use Closure;
use Markommerce\Config\Contracts\ConfigCacheInterface;

class RequestConfigCache implements ConfigCacheInterface
{
    /** @var array<string, mixed> */
    private array $store = [];

    /**
     * @param Closure(): mixed $loader
     */
    public function get(
        string $cacheKey,
        Closure $loader,
    ): mixed {
        if (array_key_exists($cacheKey, $this->store)) {
            return $this->store[$cacheKey];
        }

        $value = $loader();
        $this->store[$cacheKey] = $value;

        return $value;
    }

    public function invalidatePrefix(string $configKey): void
    {
        $prefix = $configKey . '|';

        foreach (array_keys($this->store) as $key) {
            if ($key === $configKey || str_starts_with($key, $prefix)) {
                unset($this->store[$key]);
            }
        }
    }

    public function clear(): void
    {
        $this->store = [];
    }
}
