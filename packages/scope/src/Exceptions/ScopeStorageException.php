<?php

declare(strict_types=1);

namespace Markommerce\Scope\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown when a storage operation fails due to missing scope columns or invalid state.
 */
class ScopeStorageException extends MarkoException
{
    public static function missingColumn(
        string $column,
        string $table,
    ): self {
        return new self(
            message: "Column '$column' is missing on table '$table' and is required for scope storage",
            context: "Saving scope data to table '$table'",
            suggestion: "Add the '$column' column to the '$table' table via a migration",
        );
    }

    public static function defaultScopeWrite(
        string $axis,
        string $defaultScope,
    ): self
    {
        return new self(
            message: "Cannot write a scope override at the default scope '$axis:$defaultScope' — default-scope values must be stored as base entity properties",
            context: "Writing scope override at '$axis:$defaultScope', which is the default scope for axis '$axis'",
            suggestion: "Edit the entity's base property directly instead of using setOverride() or clearOverride() with the default scope '$axis:$defaultScope'",
        );
    }
}
