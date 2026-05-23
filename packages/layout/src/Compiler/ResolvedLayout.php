<?php

declare(strict_types=1);

namespace Markommerce\Layout\Compiler;

use Markommerce\Layout\Contracts\Operation;
use Markommerce\Layout\Provide;
use Markommerce\Layout\ProvideHandle;

class ResolvedLayout
{
    /**
     * @param array<int, string>|string $handle
     * @param array<string, list<ResolvedPlace>|ResolvedRepeatSlot> $slots
     * @param list<Provide> $context
     * @param list<ProvideHandle> $handleProviders
     * @param list<Operation> $operations original layout operations preserved for runtime tree merge
     */
    public function __construct(
        public array|string $handle,
        public string $handleKey,
        public ?string $template,
        public array $slots,
        public array $context,
        public array $handleProviders = [],
        public array $operations = [],
    ) {}
}
