<?php

declare(strict_types=1);

namespace Markommerce\Layout\Operation;

use Markommerce\Layout\Contracts\Operation;

readonly class WrapWith implements Operation
{
    /**
     * @param class-string $decorator
     */
    public function __construct(
        public string $name,
        public string $decorator,
    ) {}
}
