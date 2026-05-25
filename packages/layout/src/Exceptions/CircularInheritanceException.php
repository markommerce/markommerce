<?php

declare(strict_types=1);

namespace Markommerce\Layout\Exceptions;

class CircularInheritanceException extends LayoutException
{
    /**
     * @param list<string> $chain
     */
    public static function forChain(array $chain): self
    {
        $chainStr = implode(' → ', $chain);

        return new self(
            message: "Circular inheritance detected in handle chain: $chainStr.",
            context: "Resolving handle inheritance — the chain '$chainStr' loops back to a previously visited handle.",
            suggestion: "Break the cycle by removing the circular 'inherits:' reference in one of the handles: " . implode(
                ', ',
                array_unique($chain)
            ) . '.',
        );
    }
}
