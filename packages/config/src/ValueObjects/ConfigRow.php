<?php

declare(strict_types=1);

namespace Markommerce\Config\ValueObjects;

use DateTimeImmutable;

readonly class ConfigRow
{
    /**
     * @param array<string, mixed> $overrides
     */
    public function __construct(
        public private(set) string $key,
        public private(set) mixed $value,
        public private(set) array $overrides,
        public private(set) int $version,
        public private(set) ?DateTimeImmutable $updatedAt = null,
    ) {}

    public function withGlobal(mixed $value): self
    {
        return new self(
            key: $this->key,
            value: $value,
            overrides: $this->overrides,
            version: $this->version,
            updatedAt: $this->updatedAt,
        );
    }

    public function withoutGlobal(): self
    {
        return new self(
            key: $this->key,
            value: null,
            overrides: $this->overrides,
            version: $this->version,
            updatedAt: $this->updatedAt,
        );
    }

    public function withOverride(
        string $signature,
        mixed $value,
    ): self
    {
        return new self(
            key: $this->key,
            value: $this->value,
            overrides: array_merge($this->overrides, [$signature => $value]),
            version: $this->version,
            updatedAt: $this->updatedAt,
        );
    }

    public function withoutOverride(string $signature): self
    {
        $overrides = $this->overrides;
        unset($overrides[$signature]);

        return new self(
            key: $this->key,
            value: $this->value,
            overrides: $overrides,
            version: $this->version,
            updatedAt: $this->updatedAt,
        );
    }
}
