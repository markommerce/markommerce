<?php

declare(strict_types=1);

namespace Markommerce\Layout\Source;

use Markommerce\Layout\Contracts\SourceInterface;

readonly class RouteSource implements SourceInterface
{
    public function __construct(
        public string $name,
        public string $as,
    ) {}
}
