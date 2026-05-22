<?php

declare(strict_types=1);

namespace Markommerce\Layout;

readonly class Provide
{
    public function __construct(
        public string $token,
        public string $provider,
        public array $props,
    ) {}
}
