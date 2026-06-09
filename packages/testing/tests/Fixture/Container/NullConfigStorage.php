<?php

declare(strict_types=1);

namespace Markommerce\Testing\Tests\Fixture\Container;

use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\ValueObjects\ConfigRow;

/**
 * A no-op ConfigStorageInterface for unit tests that need a storage binding
 * but do not perform any actual config reads or writes.
 */
class NullConfigStorage implements ConfigStorageInterface
{
    public function load(string $key): ?ConfigRow
    {
        return null;
    }

    /**
     * @param list<string> $keys
     * @return array<string, ConfigRow>
     */
    public function loadMany(array $keys): array
    {
        return [];
    }

    public function compareAndSave(
        string $key,
        ConfigRow $row,
        int $expectedVersion,
    ): bool {
        return true;
    }
}
