<?php

declare(strict_types=1);

namespace Markommerce\Layout\Cache;

use Markommerce\Layout\Provide;

readonly class PreparedTree
{
    /**
     * @param array<string, list<PreparedPlace>|PreparedRepeatSlot> $slots
     * @param list<Provide> $context
     */
    public function __construct(
        public string $handleKey,
        public ?string $template,
        public array $slots,
        public array $context,
    ) {}
}
