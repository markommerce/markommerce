<?php

declare(strict_types=1);

namespace Markommerce\Layout\Source;

use Markommerce\Layout\Contracts\SourceInterface;

readonly class ParentDataSource implements SourceInterface
{
    public function __construct(
        public string $key,
        public string $as,
    ) {}
}
