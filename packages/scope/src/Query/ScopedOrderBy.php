<?php

declare(strict_types=1);

namespace Markommerce\Scope\Query;

use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\Query\EntityQueryBuilderInterface;
use Marko\Database\Query\QuerySpecification;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\ScopeConfigurationException;
use Markommerce\Scope\Exceptions\ScopeContextException;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;

readonly class ScopedOrderBy implements QuerySpecification
{
    /**
     * @param class-string $entityClass
     */
    public function __construct(
        public string $property,
        private ScopeMetadataFactory $scopeMetadataFactory,
        private ScopeContext $scopeContext,
        private ScopeSortRendererInterface $scopeSortRenderer,
        private string $entityClass,
        public string $direction = 'asc',
    ) {}

    /**
     * @throws ScopeContextException|ScopeConfigurationException|UnknownAxisException|InvalidColumnException
     */
    public function apply(EntityQueryBuilderInterface $builder): void
    {
        $metadata = $this->scopeMetadataFactory->for($this->entityClass);

        if (!$metadata->isScoped($this->property)) {
            throw ScopeContextException::propertyNotScoped($this->property, $this->entityClass);
        }

        $axes = $metadata->axesForProperty($this->property);
        $paths = [];

        foreach ($axes as $axis) {
            $currentPath = $this->scopeContext->get($axis);

            if ($currentPath === null) {
                continue;
            }

            $hierarchy = $this->scopeContext->registry()->getHierarchy($axis);
            $walkedPaths = $hierarchy->walkUp($currentPath);

            foreach ($walkedPaths as $path) {
                $paths[] = ['axis' => $axis, 'path' => $path];
            }
        }

        if ($paths === []) {
            $builder->orderBy($this->property, strtoupper($this->direction));

            return;
        }

        $expression = new ScopeSortExpression(
            property: $this->property,
            column: $this->property,
            paths: $paths,
            direction: $this->direction,
        );

        $sql = $this->scopeSortRenderer->render($expression);
        $builder->orderByRaw($sql, strtoupper($this->direction));
    }
}
