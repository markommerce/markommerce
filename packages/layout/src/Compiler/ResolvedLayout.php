<?php

declare(strict_types=1);

namespace Markommerce\Layout\Compiler;

use Markommerce\Layout\Provide;
use Markommerce\Layout\ProvideHandle;

class ResolvedLayout
{
    /**
     * @param array<int, string>|string $handle
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $slots
     * @param list<Provide> $context
     * @param list<ProvideHandle> $handleProviders
     */
    public function __construct(
        public array|string $handle,
        public string $handleKey,
        public ?string $template,
        public array $slots,
        public array $context,
        public array $handleProviders = [],
    ) {}
}
