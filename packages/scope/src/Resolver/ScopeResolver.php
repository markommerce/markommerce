<?php

declare(strict_types=1);

namespace Markommerce\Scope\Resolver;

use Marko\Database\Entity\Entity;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\ScopeContextException;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Exceptions\UnknownScopeException;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Resolution\ScopeWalker;
use Markommerce\Scope\Scope;
use Markommerce\Scope\Storage\HasScopesInterface;

readonly class ScopeResolver
{
    public function __construct(
        private ScopeMetadataFactory $scopeMetadataFactory,
        private ScopeWalker $scopeWalker,
        private ScopeContext $scopeContext,
    ) {}

    /**
     * @throws ScopeContextException|UnknownAxisException|UnknownScopeException
     */
    public function resolved(
        Entity $entity,
        string $property,
    ): mixed {
        $entityClass = get_class($entity);

        if (!property_exists($entity, $property)) {
            throw ScopeContextException::unknownProperty($entityClass, $property);
        }

        $axes = $this->scopeMetadataFactory->for($entityClass)->axesForProperty($property);
        $storage = $this->findStorage($entity);

        if ($storage !== null) {
            $result = $this->scopeWalker->walk(
                $storage,
                $property,
                $axes,
                $this->scopeContext,
                $this->scopeContext->registry(),
            );

            if ($result->isFound()) {
                return $result->value();
            }
        }

        return $entity->{$property};
    }

    /**
     * @throws UnknownAxisException|UnknownScopeException
     */
    public function resolvedAt(
        Entity $entity,
        string $property,
        Scope $scope,
    ): mixed {
        $entityClass = get_class($entity);
        $axes = $this->scopeMetadataFactory->for($entityClass)->axesForProperty($property);
        $storage = $this->findStorage($entity);

        if ($storage !== null) {
            $result = $this->scopeWalker->walkAt($storage, $property, $axes, $scope, $this->scopeContext->registry());

            if ($result->isFound()) {
                return $result->value();
            }
        }

        return $entity->{$property};
    }

    /**
     * @throws ScopeContextException|UnknownAxisException
     */
    public function setOverride(
        Entity $entity,
        string $property,
        mixed $value,
        Scope $scope,
    ): void {
        $storage = $this->assertScopedAndFindStorage($entity, $property);

        if ($storage === null) {
            $entityClass = get_class($entity);

            throw new ScopeContextException(
                message: "Entity '$entityClass' has no scope storage: it must implement HasScopesInterface or have a companion that does.",
                context: "Setting scope override for property '$property' on '$entityClass'",
                suggestion: "Add 'use HasScopes; implements HasScopesInterface;' to '$entityClass', or register and attach a companion class that implements HasScopesInterface.",
            );
        }

        $storage->setOverride($scope->toString(), $property, $value);
    }

    /**
     * @throws ScopeContextException|UnknownAxisException
     */
    public function clearOverride(
        Entity $entity,
        string $property,
        Scope $scope,
    ): void {
        $storage = $this->assertScopedAndFindStorage($entity, $property);

        if ($storage === null) {
            return;
        }

        $storage->clearOverride($scope->toString(), $property);
    }

    /**
     * @throws ScopeContextException|UnknownAxisException
     */
    private function assertScopedAndFindStorage(
        Entity $entity,
        string $property,
    ): ?HasScopesInterface {
        $entityClass = get_class($entity);

        if (!$this->scopeMetadataFactory->for($entityClass)->isScoped($property)) {
            throw ScopeContextException::propertyNotScoped($property, $entityClass);
        }

        return $this->findStorage($entity);
    }

    private function findStorage(Entity $entity): ?HasScopesInterface
    {
        if ($entity instanceof HasScopesInterface) {
            return $entity;
        }

        return array_find(
            $entity->companions(),
            fn ($companion) => $companion instanceof HasScopesInterface,
        );
    }
}
