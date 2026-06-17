<?php

declare(strict_types=1);

namespace Markommerce\Indexer\Exceptions;

class UnknownIndexException extends IndexerException
{
    /**
     * @param list<string> $knownNames
     */
    public static function forName(
        string $name,
        array $knownNames,
    ): self
    {
        $known = $knownNames === [] ? 'none registered' : implode(', ', $knownNames);

        return new self(
            message: "Index '$name' is not registered.",
            context: "Attempted to resolve index '$name' from the registry, but no indexer with that name exists.",
            suggestion: "Register the indexer with IndexerRegistry::register() before use. Known indexes: $known.",
        );
    }
}
