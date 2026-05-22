<?php

declare(strict_types=1);

namespace Markommerce\Layout;

use Markommerce\Layout\Contracts\Operation;

readonly class LayoutExtension
{
    /**
     * @param array<int, string>|string $handle
     * @param list<Operation> $operations
     */
    public function __construct(
        public array|string $handle,
        public array $operations,
        public int $priority = 0,
    ) {}
}
