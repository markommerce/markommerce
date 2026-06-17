<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Filtering;

readonly class FilterSelection
{
    /** @param array<string, list<string>> $filters */
    public function __construct(
        private array $filters = [],
    ) {}

    /** @return list<string> */
    public function forKey(string $key): array
    {
        return $this->filters[$key] ?? [];
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->filters);
    }

    public function isEmpty(): bool
    {
        return $this->filters === [];
    }

    public function without(string $key): self
    {
        $filters = $this->filters;
        unset($filters[$key]);

        return new self($filters);
    }
}
