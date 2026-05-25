<?php

declare(strict_types=1);

namespace Markommerce\Layout\Exceptions;

class RepeatTypeMismatchException extends LayoutException
{
    public static function forItem(
        string $yieldsType,
        string $actualItemType,
    ): self
    {
        return new self(
            message: "Repeat type mismatch: yields type '$yieldsType' but item is '$actualItemType'.",
            context: "Iterating repeat block — loop declares yielded items as '$yieldsType' but found '$actualItemType'.",
            suggestion: "Ensure the data source yields items of type '$yieldsType' or update the repeat block's type declaration.",
        );
    }

    public static function forItemWithChain(
        string $yieldsType,
        string $actualItemType,
        string $chain,
    ): self
    {
        return new self(
            message: "Repeat type mismatch: yields type '$yieldsType' but item is '$actualItemType'.",
            context: "Iterating repeat block at [$chain] — loop declares yielded items as '$yieldsType' but found '$actualItemType'.",
            suggestion: "Ensure the data source yields items of type '$yieldsType' or update the repeat block's type declaration.",
        );
    }
}
