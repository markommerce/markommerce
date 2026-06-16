<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeIndex;

use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogAttribute\Definition\ProductAttributeDefinitions;
use Markommerce\CatalogAttributeIndex\Entity\ProductAttributeIndexEntry;
use Markommerce\CatalogAttributeIndex\Repository\ProductAttributeIndexRepository;
use Markommerce\CatalogAttributeScope\ScopedProductAttributeAccessor;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

class IndexedAttributeReader
{
    public function __construct(
        private readonly ProductAttributeIndexRepository $productAttributeIndexRepository,
        private readonly ScopedProductAttributeAccessor $scopedProductAttributeAccessor,
        private readonly ProductAttributeDefinitions $productAttributeDefinitions,
        private readonly SignatureCandidateEnumerator $signatureCandidateEnumerator,
        private readonly ScopeContext $scopeContext,
    ) {}

    public function resolve(
        Product $product,
        string $code,
    ): mixed
    {
        $def = $this->productAttributeDefinitions->findByCode($code);

        if ($def === null) {
            return $this->scopedProductAttributeAccessor->resolve($product, $code);
        }

        $axes = $def->config()['axes'] ?? [];
        $candidates = $this->signatureCandidateEnumerator->enumerate($axes, $this->scopeContext);

        $productId = (int) $product->id;

        // Walk candidates in resolution order — first with rows wins
        foreach ($candidates as $candidate) {
            $rows = $this->productAttributeIndexRepository->findValues($productId, $code, $candidate->toString());

            if ($rows !== []) {
                return $this->extractValue($rows);
            }
        }

        // Fall back to base (empty) signature
        $baseRows = $this->productAttributeIndexRepository->findValues($productId, $code, '');

        if ($baseRows !== []) {
            return $this->extractValue($baseRows);
        }

        // No index row for any candidate or base — delegate to live accessor
        return $this->scopedProductAttributeAccessor->resolve($product, $code);
    }

    /**
     * @param list<ProductAttributeIndexEntry> $rows
     */
    private function extractValue(array $rows): mixed
    {
        $kind = $rows[0]->valueKind;

        if ($kind === 'multiselect') {
            return array_map(fn (ProductAttributeIndexEntry $row): mixed => $row->valueText, $rows);
        }

        $row = $rows[0];

        return match ($kind) {
            'bool' => $row->valueBool,
            'int', 'decimal' => $row->valueNumber,
            default => $row->valueText,
        };
    }
}
