<?php

declare(strict_types=1);

namespace Markommerce\Layout\Operation;

use Markommerce\Layout\Contracts\Operation;

readonly class ReplaceProps implements Operation
{
    /**
     * @param array<string, mixed> $props
     */
    public function __construct(
        public string $name,
        public array $props,
    ) {}
}
