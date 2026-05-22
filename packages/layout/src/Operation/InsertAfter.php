<?php

declare(strict_types=1);

namespace Markommerce\Layout\Operation;

use Markommerce\Layout\Contracts\Operation;
use Markommerce\Layout\Place;

readonly class InsertAfter implements Operation
{
    public function __construct(
        public string $anchorName,
        public Place $placement,
    ) {}
}
