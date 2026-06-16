<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeIndex\Tests\Support;

use Marko\Database\Connection\StatementInterface;

class FakeStatement implements StatementInterface
{
    public function execute(array $bindings = []): bool
    {
        return true;
    }

    /** @return array<array<string, mixed>> */
    public function fetchAll(): array
    {
        return [];
    }

    /** @return array<string, mixed>|null */
    public function fetch(): ?array
    {
        return null;
    }

    public function rowCount(): int
    {
        return 0;
    }
}
