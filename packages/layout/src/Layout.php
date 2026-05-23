<?php

declare(strict_types=1);

namespace Markommerce\Layout;

use InvalidArgumentException;
use Markommerce\Layout\Contracts\Operation;

readonly class Layout
{
    /**
     * @param array<int, string>|string|null $handle
     * @param class-string|null $extends
     * @param list<Provide> $context
     * @param array<string, list<Place>|Slot> $slots
     * @param list<Operation> $operations
     * @param list<ProvideHandle> $handleProviders
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        public array|string|null $handle,
        public ?string $extends,
        public ?string $inherits = null,
        public array $context = [],
        public array $slots = [],
        public array $operations = [],
        public array $handleProviders = [],
        public ?string $template = null,
    ) {
        if ($inherits !== null && $handle !== null) {
            $handleKey = is_array($handle)
                ? $handle[0] . '::' . $handle[1]
                : $handle;

            if ($inherits === $handleKey) {
                throw new InvalidArgumentException(
                    "A layout cannot inherit from itself. Handle '$handleKey' sets inherits: '$inherits'.",
                );
            }
        }
    }
}
