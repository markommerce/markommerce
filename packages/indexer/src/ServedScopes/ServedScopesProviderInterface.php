<?php

declare(strict_types=1);

namespace Markommerce\Indexer\ServedScopes;

use Markommerce\Scope\Signature\ScopeSignature;

interface ServedScopesProviderInterface
{
    /**
     * Return non-empty ScopeSignatures for the given axis subset.
     *
     * @param list<string> $axes
     * @return list<ScopeSignature>
     */
    public function signatures(array $axes): array;
}
