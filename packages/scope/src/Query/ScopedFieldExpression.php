<?php

declare(strict_types=1);

namespace Markommerce\Scope\Query;

use Markommerce\Scope\Signature\ScopeSignature;

readonly class ScopedFieldExpression
{
    /**
     * @param list<ScopeSignature> $candidateSignatures in descending-score order
     */
    public function __construct(
        public string $property,
        public string $column,
        public array $candidateSignatures,
        public string $jsonColumn = 'scopes',
    ) {}
}
