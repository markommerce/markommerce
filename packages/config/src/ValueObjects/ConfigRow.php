<?php

declare(strict_types=1);

namespace Markommerce\Config\ValueObjects;

use DateTimeImmutable;

readonly class ConfigRow
{
    public function __construct(
        public private(set) string $key,
        public private(set) mixed $value,
        public private(set) int $version,
        public private(set) ?DateTimeImmutable $updatedAt = null,
    ) {}

    public function withGlobal(mixed $value): self
    {
        return new self(
            key: $this->key,
            value: $value,
            version: $this->version,
            updatedAt: $this->updatedAt,
        );
    }

    public function withoutGlobal(): self
    {
        return new self(
            key: $this->key,
            value: null,
            version: $this->version,
            updatedAt: $this->updatedAt,
        );
    }
}
