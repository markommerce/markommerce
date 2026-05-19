<?php

declare(strict_types=1);

namespace Markommerce\Scope\Resolver;

use Marko\Database\Entity\Entity;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\InvalidSignatureForAttributeException;
use Markommerce\Scope\Exceptions\ScopeContextException;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Exceptions\UnknownScopeException;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\Resolution\ScopeWalker;
use Markommerce\Scope\Signature\ScopeSignature;
use Markommerce\Scope\Signature\ScopeSignatureValidator;
use Markommerce\Scope\Storage\HasScopesInterface;

readonly class ScopeResolver
{
    public function __construct(
        private ScopeMetadataFactory $scopeMetadataFactory,
        private ScopeWalker $scopeWalker,
        private ScopeContext $scopeContext,
        private ScopeSignatureValidator $scopeSignatureValidator,
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
        ScopeSignature $signature,
    ): mixed {
        $entityClass = get_class($entity);
        $axes = $this->scopeMetadataFactory->for($entityClass)->axesForProperty($property);
        $storage = $this->findStorage($entity);

        if ($storage !== null) {
            $result = $this->scopeWalker->walkAt(
                $storage,
                $property,
                $axes,
                $signature,
                $this->scopeContext->registry(),
            );

            if ($result->isFound()) {
                return $result->value();
            }
        }

        return $entity->{$property};
    }

    /**
     * @throws ScopeContextException|UnknownAxisException|InvalidSignatureForAttributeException
     */
    public function setOverride(
        Entity $entity,
        string $property,
        mixed $value,
        ScopeSignature $signature,
    ): void {
        $entityClass = get_class($entity);
        $attributeAxes = $this->assertScopedAndGetAxes($entity, $property);
        $this->scopeSignatureValidator->validate($signature, $attributeAxes);

        $storage = $this->findStorage($entity);

        if ($storage === null) {
            throw new ScopeContextException(
                message: "Entity '$entityClass' has no scope storage: it must implement HasScopesInterface or have a companion that does.",
                context: "Setting scope override for property '$property' on '$entityClass'",
                suggestion: "Add 'use HasScopes; implements HasScopesInterface;' to '$entityClass', or register and attach a companion class that implements HasScopesInterface.",
            );
        }

        $storage->setOverride($signature->toString(), $property, $value);
    }

    /**
     * @throws ScopeContextException|UnknownAxisException|InvalidSignatureForAttributeException
     */
    public function clearOverride(
        Entity $entity,
        string $property,
        ScopeSignature $signature,
    ): void {
        $attributeAxes = $this->assertScopedAndGetAxes($entity, $property);
        $this->scopeSignatureValidator->validate($signature, $attributeAxes);

        $storage = $this->findStorage($entity);

        if ($storage === null) {
            return;
        }

        $storage->clearOverride($signature->toString(), $property);
    }

    /**
     * @return list<string>
     *
     * @throws ScopeContextException|UnknownAxisException
     */
    private function assertScopedAndGetAxes(
        Entity $entity,
        string $property,
    ): array {
        $entityClass = get_class($entity);
        $metadata = $this->scopeMetadataFactory->for($entityClass);

        if (!$metadata->isScoped($property)) {
            throw ScopeContextException::propertyNotScoped($property, $entityClass);
        }

        return $metadata->axesForProperty($property);
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
