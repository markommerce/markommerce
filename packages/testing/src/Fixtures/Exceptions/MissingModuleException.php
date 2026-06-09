<?php

declare(strict_types=1);

namespace Markommerce\Testing\Fixtures\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class MissingModuleException extends MarkoException
{
    public static function forModule(
        string $module,
        string $requiredFor,
    ): self
    {
        return new self(
            message: "The module '$module' is not loaded in the current store profile",
            context: "While trying to use '$requiredFor' in a fixture factory",
            suggestion: "Use a StoreProfile that includes the '$module' module, e.g. StoreProfile::of(\$vendorDir, 'markommerce/catalog', '$module', 'marko/database-pgsql')",
        );
    }
}
