<?php

declare(strict_types=1);

namespace Markommerce\ConfigScope\Contracts;

interface ScopedConfigStorageInterface
{
    /**
     * Load all overrides for a given config key.
     *
     * @return array<string, mixed> signature => raw value
     */
    public function loadOverrides(string $key): array;

    /**
     * Load overrides for multiple config keys at once.
     *
     * @param list<string> $keys
     * @return array<string, array<string, mixed>> configKey => (signature => value)
     */
    public function loadManyOverrides(array $keys): array;

    /**
     * Save (or replace) an override for a (key, signature) pair.
     */
    public function saveOverride(
        string $key,
        string $signature,
        mixed $value,
    ): void;

    /**
     * Remove an override for a (key, signature) pair.
     */
    public function deleteOverride(
        string $key,
        string $signature,
    ): void;
}
