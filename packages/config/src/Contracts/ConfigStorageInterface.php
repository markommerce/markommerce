<?php

declare(strict_types=1);

namespace Markommerce\Config\Contracts;

use Markommerce\Config\ValueObjects\ConfigRow;

interface ConfigStorageInterface
{
    public function load(string $key): ?ConfigRow;

    /**
     * @param list<string> $keys
     * @return array<string, ConfigRow>
     */
    public function loadMany(array $keys): array;

    public function compareAndSave(
        string $key,
        ConfigRow $row,
        int $expectedVersion,
    ): bool;
}
