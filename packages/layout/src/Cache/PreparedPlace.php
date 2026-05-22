<?php

declare(strict_types=1);

namespace Markommerce\Layout\Cache;

readonly class PreparedPlace
{
    /**
     * @param array<string, mixed> $props
     * @param array<string, list<PreparedPlace>|PreparedRepeatSlot> $slots
     * @param list<string> $decorators
     */
    public function __construct(
        public string $component,
        public ?string $name,
        public array $props,
        public array $slots,
        public array $decorators = [],
    ) {}
}
