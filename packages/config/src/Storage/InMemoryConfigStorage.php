<?php

declare(strict_types=1);

namespace Markommerce\Config\Storage;

use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\ValueObjects\ConfigRow;

class InMemoryConfigStorage implements ConfigStorageInterface
{
    /** @var array<string, ConfigRow> */
    private array $store = [];

    public function load(string $key): ?ConfigRow
    {
        return $this->store[$key] ?? null;
    }

    /**
     * @param list<string> $keys
     * @return array<string, ConfigRow>
     */
    public function loadMany(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            if (isset($this->store[$key])) {
                $result[$key] = $this->store[$key];
            }
        }

        return $result;
    }

    public function compareAndSave(
        string $key,
        ConfigRow $row,
        int $expectedVersion,
    ): bool {
        $isEmpty = $row->value === null;
        $exists = isset($this->store[$key]);
        $storedVersion = $exists ? $this->store[$key]->version : 0;

        if ($isEmpty && !$exists && $expectedVersion === 0) {
            return true;
        }

        if ($isEmpty && $exists && $storedVersion === $expectedVersion) {
            unset($this->store[$key]);

            return true;
        }

        if ($isEmpty) {
            return false;
        }

        if (!$exists && $expectedVersion === 0) {
            $this->store[$key] = new ConfigRow(
                key: $key,
                value: $row->value,
                version: 1,
                updatedAt: $row->updatedAt,
            );

            return true;
        }

        if ($exists && $storedVersion === $expectedVersion) {
            $this->store[$key] = new ConfigRow(
                key: $key,
                value: $row->value,
                version: $expectedVersion + 1,
                updatedAt: $row->updatedAt,
            );

            return true;
        }

        return false;
    }
}
