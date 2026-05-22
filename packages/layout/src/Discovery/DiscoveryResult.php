<?php

declare(strict_types=1);

namespace Markommerce\Layout\Discovery;

readonly class DiscoveryResult
{
    /**
     * @param list<DiscoveredLayout> $layouts
     * @param list<DiscoveredExtension> $extensions
     */
    public function __construct(
        public array $layouts,
        public array $extensions,
    ) {}
}
