<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttribute;

use InvalidArgumentException;
use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Contracts\AttributeValueAccessorInterface;
use Markommerce\Attribute\Exceptions\AttributeDefinitionNotFoundException;
use Markommerce\Attribute\Exceptions\InvalidAttributeOptionException;
use Markommerce\Attribute\Exceptions\InvalidAttributeValueException;
use Markommerce\Attribute\Exceptions\UnknownAttributeTypeException;
use Markommerce\Attribute\Type\AttributeBacking;
use Markommerce\Attribute\Validation\AttributeValueValidator;
use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogAttribute\Definition\ProductAttributeDefinitions;
use Markommerce\CatalogAttribute\Definition\StaticAttributeProvider;
use Markommerce\CatalogAttribute\Entity\ProductAttributeValues;

class ProductAttributeAccessor implements AttributeValueAccessorInterface
{
    public function __construct(
        private readonly ProductAttributeDefinitions $productAttributeDefinitions,
        private readonly AttributeValueValidator $attributeValueValidator,
        private readonly AttributeDefinitionRepositoryInterface $attributeDefinitionRepository,
        private readonly StaticAttributeProvider $staticAttributeProvider,
    ) {}

    /**
     * @throws AttributeDefinitionNotFoundException|InvalidAttributeValueException|InvalidAttributeOptionException|UnknownAttributeTypeException
     */
    public function set(object $entity, string $code, mixed $raw): void
    {
        $product = $this->guardProduct($entity);

        $def = $this->productAttributeDefinitions->findByCode($code);

        if ($def === null) {
            throw AttributeDefinitionNotFoundException::forCode('product', $code);
        }

        $allowedOptions = [];

        if (in_array($def->type, ['select', 'multiselect'], strict: true)) {
            $options = $this->attributeDefinitionRepository->optionsFor($def);
            $allowedOptions = array_map(fn ($o) => $o->value, $options);
        }

        $value = $this->attributeValueValidator->validate($def, $raw, $allowedOptions);

        if ($def->backing() === AttributeBacking::Column) {
            $property = $def->config()['property'];
            $product->{$property} = $value;
        } else {
            $companion = $this->resolveCompanion($product);
            $companion->set($code, $value);
        }
    }

    /**
     * @throws AttributeDefinitionNotFoundException|UnknownAttributeTypeException
     */
    public function get(object $entity, string $code): mixed
    {
        $product = $this->guardProduct($entity);

        $def = $this->productAttributeDefinitions->findByCode($code);

        if ($def === null) {
            throw AttributeDefinitionNotFoundException::forCode('product', $code);
        }

        if ($def->backing() === AttributeBacking::Column) {
            $property = $def->config()['property'];
            $value = $product->{$property};

            if ($value === null && $def->defaultValue !== null) {
                return $this->attributeValueValidator->validate($def, $def->defaultValue);
            }

            return $value;
        }

        $companion = $product->companion(ProductAttributeValues::class);

        if ($companion !== null && $companion->has($code)) {
            return $companion->get($code);
        }

        if ($def->defaultValue !== null) {
            return $this->attributeValueValidator->validate($def, $def->defaultValue);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(object $entity): array
    {
        $product = $this->guardProduct($entity);

        $result = [];

        foreach ($this->staticAttributeProvider->definitions() as $staticDef) {
            $result[$staticDef->code] = $this->get($product, $staticDef->code);
        }

        $companion = $product->companion(ProductAttributeValues::class);

        if ($companion !== null) {
            foreach ($companion->all() as $code => $value) {
                $result[$code] = $value;
            }
        }

        return $result;
    }

    /**
     * @throws AttributeDefinitionNotFoundException
     */
    public function clear(object $entity, string $code): void
    {
        $product = $this->guardProduct($entity);

        $def = $this->productAttributeDefinitions->findByCode($code);

        if ($def === null) {
            throw AttributeDefinitionNotFoundException::forCode('product', $code);
        }

        if ($def->backing() === AttributeBacking::Column) {
            // Column-backed attributes are non-clearable: no-op (property retains its current value).
            return;
        }

        $companion = $product->companion(ProductAttributeValues::class);

        if ($companion !== null) {
            $companion->clear($code);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function guardProduct(object $entity): Product
    {
        if (!$entity instanceof Product) {
            throw new InvalidArgumentException(
                'ProductAttributeAccessor only supports Product entities, got ' . $entity::class,
            );
        }

        return $entity;
    }

    private function resolveCompanion(Product $product): ProductAttributeValues
    {
        $companion = $product->companion(ProductAttributeValues::class);

        if ($companion === null) {
            $companion = new ProductAttributeValues();
            $product->attachCompanion($companion);
        }

        return $companion;
    }
}
