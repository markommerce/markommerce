<?php

declare(strict_types=1);

namespace Markommerce\ConfigScope\Storage;

use Markommerce\ConfigScope\Contracts\ScopedConfigStorageInterface;

class InMemoryScopedConfigStorage implements ScopedConfigStorageInterface
{
    /** @var array<string, array<string, mixed>> configKey => (signature => value) */
    private array $store = [];

    /**
     * @return array<string, mixed> signature => raw value
     */
    public function loadOverrides(string $key): array
    {
        return $this->store[$key] ?? [];
    }

    /**
     * @param list<string> $keys
     * @return array<string, array<string, mixed>> configKey => (signature => value)
     */
    public function loadManyOverrides(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->store[$key] ?? [];
        }

        return $result;
    }

    public function saveOverride(
        string $key,
        string $signature,
        mixed $value,
    ): void
    {
        if (!isset($this->store[$key])) {
            $this->store[$key] = [];
        }

        $this->store[$key][$signature] = $value;
    }

    public function deleteOverride(
        string $key,
        string $signature,
    ): void
    {
        unset($this->store[$key][$signature]);
    }
}
