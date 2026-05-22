<?php

declare(strict_types=1);

namespace Markommerce\Layout\Source;

use Markommerce\Layout\Contracts\SourceInterface;

readonly class QuerySource implements SourceInterface
{
    public function __construct(
        public string $name,
        public mixed $default,
        public string $as,
    ) {}
}
