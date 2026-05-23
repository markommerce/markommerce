<?php

declare(strict_types=1);

namespace Markommerce\Layout;

readonly class ProvideHandle
{
    /**
     * @param class-string $provider
     * @param array<string, mixed> $props
     */
    public function __construct(
        public string $provider,
        public array $props,
    ) {}
}
