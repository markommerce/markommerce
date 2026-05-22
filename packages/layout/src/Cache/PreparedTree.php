<?php

declare(strict_types=1);

namespace Markommerce\Layout\Cache;

use Markommerce\Layout\Provide;
use Markommerce\Layout\ProvideHandle;

readonly class PreparedTree
{
    /**
     * @param array<string, list<PreparedPlace>|PreparedRepeatSlot> $slots
     * @param list<Provide> $context
     * @param list<ProvideHandle> $handleProviders
     * @param list<string> $placementNames named placement names collected at compile time, used by TreeMerger for runtime collision detection
     */
    public function __construct(
        public string $handleKey,
        public ?string $template,
        public array $slots,
        public array $context,
        public array $handleProviders = [],
        public array $placementNames = [],
    ) {}
}
