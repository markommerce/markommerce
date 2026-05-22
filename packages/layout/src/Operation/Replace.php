<?php

declare(strict_types=1);

namespace Markommerce\Layout\Operation;

use Markommerce\Layout\Contracts\Operation;
use Markommerce\Layout\Place;

readonly class Replace implements Operation
{
    public function __construct(
        public string $name,
        public Place $placement,
    ) {}
}
