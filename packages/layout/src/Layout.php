<?php

declare(strict_types=1);

namespace Markommerce\Layout;

readonly class Layout
{
    /**
     * @param array<int, string>|string|null $handle
     * @param class-string|null $extends
     * @param list<Provide> $context
     * @param array<string, list<Place>|Slot> $slots
     */
    public function __construct(
        public array|string|null $handle,
        public ?string $extends,
        public array $context,
        public array $slots,
        public ?string $template = null,
    ) {}
}
