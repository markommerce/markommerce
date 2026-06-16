<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeScope\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Scope\Exceptions\ScopeStorageException;
use Markommerce\Scope\Storage\DefaultScopeGuard;
use Markommerce\Scope\Storage\HasScopesInterface;

#[Table(extends: Product::class)]
class ProductScopedAttributeValues extends Entity implements HasScopesInterface
{
    /** @var array<string, array<string, mixed>>|null */
    #[Column(name: 'scoped_attribute_values', type: 'json', nullable: true)]
    public ?array $scopedValues = null;

    /**
     * @throws ScopeStorageException
     */
    public function setOverride(
        string $signature,
        string $property,
        mixed $value,
    ): void
    {
        DefaultScopeGuard::assertWritable($signature);
        $scopedValues = $this->scopedValues ?? [];
        $scopedValues[$signature][$property] = $value;
        ksort($scopedValues[$signature]);
        ksort($scopedValues);
        $this->scopedValues = $scopedValues;
    }

    public function override(
        string $signature,
        string $property,
    ): mixed
    {
        return $this->scopedValues[$signature][$property] ?? null;
    }

    public function hasOverride(
        string $signature,
        string $property,
    ): bool
    {
        return array_key_exists($signature, $this->scopedValues ?? [])
            && array_key_exists($property, $this->scopedValues[$signature]);
    }

    /**
     * @throws ScopeStorageException
     */
    public function clearOverride(
        string $signature,
        string $property,
    ): void
    {
        DefaultScopeGuard::assertWritable($signature);
        if (!isset($this->scopedValues[$signature])) {
            return;
        }

        $scopedValues = $this->scopedValues;
        unset($scopedValues[$signature][$property]);

        if ($scopedValues[$signature] === []) {
            unset($scopedValues[$signature]);
        }

        ksort($scopedValues);
        $this->scopedValues = $scopedValues ?: null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function overrides(): array
    {
        return $this->scopedValues ?? [];
    }
}
