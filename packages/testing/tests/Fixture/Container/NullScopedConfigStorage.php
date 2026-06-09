<?php

declare(strict_types=1);

namespace Markommerce\Testing\Tests\Fixture\Container;

use Markommerce\ConfigScope\Contracts\ScopedConfigStorageInterface;

/**
 * A no-op ScopedConfigStorageInterface for unit tests that need a scoped
 * storage binding but do not perform any actual config reads or writes.
 */
class NullScopedConfigStorage implements ScopedConfigStorageInterface
{
    /**
     * @return array<string, mixed>
     */
    public function loadOverrides(string $key): array
    {
        return [];
    }

    /**
     * @param list<string> $keys
     * @return array<string, array<string, mixed>>
     */
    public function loadManyOverrides(array $keys): array
    {
        return [];
    }

    public function saveOverride(string $key, string $signature, mixed $value): void {}

    public function deleteOverride(string $key, string $signature): void {}
}
