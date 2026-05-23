<?php

declare(strict_types=1);

namespace Markommerce\Layout\Discovery;

use Markommerce\Layout\Layout;

readonly class DiscoveredLayout
{
    public function __construct(
        public Layout $layout,
        public string $sourceFile,
    ) {}
}
