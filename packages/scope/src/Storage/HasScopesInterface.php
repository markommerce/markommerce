<?php

declare(strict_types=1);

namespace Markommerce\Scope\Storage;

interface HasScopesInterface
{
    public function setOverride(
        string $signature,
        string $property,
        mixed $value,
    ): void;

    public function override(
        string $signature,
        string $property,
    ): mixed;

    public function hasOverride(
        string $signature,
        string $property,
    ): bool;

    public function clearOverride(
        string $signature,
        string $property,
    ): void;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function overrides(): array;
}
