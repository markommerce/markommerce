<?php

declare(strict_types=1);

namespace Markommerce\Layout\Discovery;

use Markommerce\Layout\LayoutExtension;

readonly class DiscoveredExtension
{
    public function __construct(
        public LayoutExtension $extension,
        public string $sourceFile,
    ) {}
}
