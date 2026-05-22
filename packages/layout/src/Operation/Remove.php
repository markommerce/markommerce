<?php

declare(strict_types=1);

namespace Markommerce\Layout\Operation;

use Markommerce\Layout\Contracts\Operation;

readonly class Remove implements Operation
{
    public function __construct(
        public string $name,
    ) {}
}
