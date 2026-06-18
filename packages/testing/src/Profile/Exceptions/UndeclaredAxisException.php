<?php

declare(strict_types=1);

namespace Markommerce\Testing\Profile\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Thrown when inScope() is given an axis the profile does not declare.
 */
class UndeclaredAxisException extends MarkoException
{
    public static function forAxis(
        string $axis,
        string $profileClass = 'StoreProfile',
    ): self {
        return new self(
            message: "Axis '$axis' is not declared in this profile.",
            context: "The $profileClass was built without the '$axis' scope axis."
                . ' Calling inScope() with an undeclared axis would trigger a low-level registry error.',
            suggestion: "Use a profile preset that includes the '$axis' axis,"
                . " e.g. StoreProfile::twoMarketsTwoLocales() for 'market',"
                . " or StoreProfile::singleMarketTwoLocales() for 'locale'.",
        );
    }
}
