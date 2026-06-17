<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeScope;

use InvalidArgumentException;
use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Exceptions\AttributeDefinitionNotFoundException;
use Markommerce\Attribute\Type\AttributeBacking;
use Markommerce\Attribute\Validation\AttributeValueValidator;
use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogAttribute\Definition\ProductAttributeDefinitions;
use Markommerce\CatalogAttribute\ProductAttributeAccessor;
use Markommerce\CatalogAttributeScope\Entity\ProductScopedAttributeValues;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\ScopeContextException;
use Markommerce\Scope\Resolution\ScopeWalker;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignature;

class ScopedProductAttributeAccessor
{
    public function __construct(
        private readonly ProductAttributeDefinitions $productAttributeDefinitions,
        private readonly AttributeValueValidator $attributeValueValidator,
        private readonly AttributeDefinitionRepositoryInterface $attributeDefinitionRepository,
        private readonly ProductAttributeAccessor $productAttributeAccessor,
        private readonly ScopeWalker $scopeWalker,
        private readonly ScopeContext $scopeContext,
        private readonly ScopeResolver $scopeResolver,
    ) {}

    /**
     * @throws AttributeDefinitionNotFoundException|ScopeContextException
     */
    public function setScoped(
        Product $product,
        string $code,
        mixed $raw,
        ScopeSignature $signature,
    ): void
    {
        $def = $this->productAttributeDefinitions->findByCode($code);

        if ($def === null) {
            throw AttributeDefinitionNotFoundException::forCode('product', $code);
        }

        if ($def->entityType() !== 'product') {
            throw new InvalidArgumentException("Attribute '$code' does not belong to entity type 'product'.");
        }

        if (!$def->scopable) {
            throw new ScopeContextException(
                message: "Attribute '$code' is not scopable.",
                context: "Setting scoped value for attribute '$code' on a product",
                suggestion: "Mark the attribute '$code' as scopable before setting scoped overrides.",
            );
        }

        $allowedOptions = [];
        if (in_array($def->type, ['select', 'multiselect'], strict: true)) {
            $options = $this->attributeDefinitionRepository->optionsFor($def);
            $allowedOptions = array_map(fn ($o) => $o->value, $options);
        }

        $value = $this->attributeValueValidator->validate($def, $raw, $allowedOptions);

        if ($def->backing() === AttributeBacking::Json) {
            $companion = $this->resolveCompanion($product);
            $companion->setOverride($signature->toString(), $code, $value);
        } else {
            $config = $def->config();
            $property = $config['property'];

            try {
                $this->scopeResolver->setOverride($product, $property, $value, $signature);
            } catch (ScopeContextException $e) {
                throw new ScopeContextException(
                    message: "Cannot set scoped override for Column-backed attribute '$code': property '$property' is not registered as a scoped field.",
                    context: "Setting scoped value for Column-backed attribute '$code' (property '$property') on a product",
                    suggestion: "Install a native-field scoping bridge (catalog-scope + catalog-locale/-market) to register '$property' as a scoped field.",
                    previous: $e,
                );
            }
        }
    }

    /**
     * @throws AttributeDefinitionNotFoundException
     */
    public function getScoped(
        Product $product,
        string $code,
        ScopeSignature $signature,
    ): mixed
    {
        $def = $this->productAttributeDefinitions->findByCode($code);

        if ($def === null) {
            throw AttributeDefinitionNotFoundException::forCode('product', $code);
        }

        if ($def->backing() === AttributeBacking::Json) {
            $companion = $product->companion(ProductScopedAttributeValues::class);

            if ($companion === null) {
                return null;
            }

            return $companion->override($signature->toString(), $code);
        }

        $config = $def->config();
        $property = $config['property'];

        return $this->scopeResolver->resolvedAt($product, $property, $signature);
    }

    /**
     * @throws AttributeDefinitionNotFoundException
     */
    public function resolve(
        Product $product,
        string $code,
    ): mixed
    {
        $def = $this->productAttributeDefinitions->findByCode($code);

        if ($def === null) {
            throw AttributeDefinitionNotFoundException::forCode('product', $code);
        }

        if ($def->backing() === AttributeBacking::Json) {
            $axes = $def->config()['axes'] ?? [];
            $companion = $product->companion(ProductScopedAttributeValues::class);

            if ($companion !== null) {
                $result = $this->scopeWalker->walk($companion, $code, $axes, $this->scopeContext);

                if ($result->isFound()) {
                    return $result->value();
                }
            }

            return $this->productAttributeAccessor->get($product, $code);
        }

        $config = $def->config();
        $property = $config['property'];

        return $this->scopeResolver->resolved($product, $property);
    }

    private function resolveCompanion(Product $product): ProductScopedAttributeValues
    {
        $companion = $product->companion(ProductScopedAttributeValues::class);

        if ($companion === null) {
            $companion = new ProductScopedAttributeValues();
            $product->attachCompanion($companion);
        }

        return $companion;
    }
}
