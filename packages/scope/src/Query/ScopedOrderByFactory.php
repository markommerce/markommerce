<?php

declare(strict_types=1);

namespace Markommerce\Scope\Query;

use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

readonly class ScopedOrderByFactory
{
    public function __construct(
        private ScopeMetadataFactory $scopeMetadataFactory,
        private ScopeContext $scopeContext,
        private ScopedFieldRendererInterface $scopedFieldRenderer,
        private SignatureCandidateEnumerator $signatureCandidateEnumerator,
    ) {}

    /**
     * @param class-string $entityClass
     */
    public function create(
        string $entityClass,
        string $property,
        string $direction = 'asc',
    ): ScopedOrderBy {
        return new ScopedOrderBy(
            property: $property,
            scopeMetadataFactory: $this->scopeMetadataFactory,
            scopeContext: $this->scopeContext,
            scopedFieldRenderer: $this->scopedFieldRenderer,
            signatureCandidateEnumerator: $this->signatureCandidateEnumerator,
            entityClass: $entityClass,
            direction: $direction,
        );
    }
}
