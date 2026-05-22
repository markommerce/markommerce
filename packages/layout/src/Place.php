<?php

declare(strict_types=1);

namespace Markommerce\Layout;

readonly class Place
{
    /**
     * @param array<string, mixed> $props
     * @param array<string, list<Place>|Slot> $slots
     */
    public function __construct(
        public string $component,
        public ?string $name,
        public array $props,
        public array $slots,
        public string $template = '',
    ) {}
}
