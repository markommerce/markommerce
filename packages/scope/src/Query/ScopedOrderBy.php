<?php

declare(strict_types=1);

namespace Markommerce\Scope\Query;

use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\Query\EntityQueryBuilderInterface;
use Marko\Database\Query\QuerySpecification;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\ScopeContextException;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

readonly class ScopedOrderBy implements QuerySpecification
{
    /**
     * @param class-string $entityClass
     */
    public function __construct(
        public string $property,
        private ScopeMetadataFactory $scopeMetadataFactory,
        private ScopeContext $scopeContext,
        private ScopedFieldRendererInterface $scopedFieldRenderer,
        private SignatureCandidateEnumerator $signatureCandidateEnumerator,
        private string $entityClass,
        public string $direction = 'asc',
    ) {}

    /**
     * @throws ScopeContextException|UnknownAxisException|InvalidColumnException
     */
    public function apply(EntityQueryBuilderInterface $builder): void
    {
        $metadata = $this->scopeMetadataFactory->for($this->entityClass);

        if (!$metadata->isScoped($this->property)) {
            throw ScopeContextException::propertyNotScoped($this->property, $this->entityClass);
        }

        $axes = $metadata->axesForProperty($this->property);
        $candidateSignatures = $this->signatureCandidateEnumerator->enumerate($axes, $this->scopeContext);

        if ($candidateSignatures === []) {
            $builder->orderBy($this->property, strtoupper($this->direction));

            return;
        }

        $expression = new ScopedFieldExpression(
            property: $this->property,
            column: $this->property,
            candidateSignatures: $candidateSignatures,
        );

        $sql = $this->scopedFieldRenderer->render($expression);
        $builder->orderByRaw($sql, strtoupper($this->direction));
    }
}
