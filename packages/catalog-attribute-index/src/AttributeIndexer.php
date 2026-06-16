<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeIndex;

use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogAttributeIndex\Entity\ProductAttributeIndexEntry;
use Markommerce\CatalogAttributeIndex\Repository\ProductAttributeIndexRepository;
use Markommerce\CatalogAttributeScope\ScopedProductAttributeAccessor;
use Markommerce\Indexer\AbstractIndexer;
use Markommerce\Indexer\ScopePassRunner;
use Markommerce\Indexer\ServedScopes\ServedScopesProviderInterface;
use Markommerce\Scope\Signature\ScopeSignature;

class AttributeIndexer extends AbstractIndexer
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private AttributeDefinitionRepositoryInterface $attributeDefinitionRepository,
        private ScopedProductAttributeAccessor $scopedProductAttributeAccessor,
        private ScopePassRunner $scopePassRunner,
        private ServedScopesProviderInterface $servedScopesProvider,
        private ProductAttributeIndexRepository $productAttributeIndexRepository,
    ) {}

    /**
     * @return iterable<int>
     */
    protected function allIds(): iterable
    {
        $rows = $this->productRepository->query()->selectRaw('id')->get();

        return array_map(fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * @param list<int> $ids
     */
    protected function indexChunk(array $ids): int
    {
        // Load products for this chunk.
        $collection = $this->productRepository->query()->whereIn('id', $ids)->getEntities();

        /** @var array<int, Product> $products */
        $products = [];

        foreach ($collection as $product) {
            /** @var Product $product */
            if ($product->id === null) {
                continue;
            }

            $products[$product->id] = $product;
        }

        // Determine indexed attribute definitions: filterable OR facetable, entity_type=product.
        // Exclude Column-backed (static) attributes — they're native columns.
        $indexedDefs = $this->resolveIndexedDefs();

        if ($indexedDefs === [] || $products === []) {
            $this->productAttributeIndexRepository->replaceForProducts($ids, []);

            return 0;
        }

        /** @var array<int, list<ProductAttributeIndexEntry>> $rowsByProduct */
        $rowsByProduct = [];

        foreach ($ids as $id) {
            $rowsByProduct[$id] = [];
        }

        // Build a map of attribute => per-attribute scoped signatures.
        // Non-scopable or empty-axes defs get no scoped passes (base-only).
        $defSignatures = $this->buildDefSignatures($indexedDefs);

        // Base pass: collect base values for all products/attributes.
        /** @var array<int, array<string, mixed>> $baseValues productId => code => value */
        $baseValues = [];

        $this->scopePassRunner->each([], function (?ScopeSignature $sig) use (
            $products,
            $indexedDefs,
            &$baseValues,
            &$rowsByProduct,
        ): void {
            // $sig is always null here since signatures=[]
            foreach ($products as $productId => $product) {
                foreach ($indexedDefs as $def) {
                    $value = $this->scopedProductAttributeAccessor->resolve($product, $def->code);

                    if ($value === null) {
                        continue;
                    }

                    // Store for skip-redundant comparison later.
                    if (!isset($baseValues[$productId])) {
                        $baseValues[$productId] = [];
                    }

                    $baseValues[$productId][$def->code] = $value;

                    // Emit base row(s).
                    $newRows = $this->buildRows($productId, $def, $value, '');
                    $rowsByProduct[$productId] = array_merge($rowsByProduct[$productId], $newRows);
                }
            }
        });

        // Scoped passes: group attributes by axis set, run each distinct axis-set once.
        // Only emit rows when the scoped value differs from the base value.
        $this->runScopedPasses($products, $indexedDefs, $defSignatures, $baseValues, $rowsByProduct);

        // Flatten all rows.
        $allRows = [];

        foreach ($rowsByProduct as $rows) {
            $allRows = array_merge($allRows, $rows);
        }

        $this->productAttributeIndexRepository->replaceForProducts($ids, $allRows);

        return count($allRows);
    }

    /**
     * @return list<AttributeDefinition>
     */
    private function resolveIndexedDefs(): array
    {
        $collection = $this->attributeDefinitionRepository
            ->query()
            ->where('entity_type', '=', 'product')
            ->getEntities();

        /** @var list<AttributeDefinition> $defs */
        $defs = [];

        foreach ($collection as $def) {
            /** @var AttributeDefinition $def */
            if (!($def->filterable || $def->facetable)) {
                continue;
            }

            // Skip Column-backed (static) attributes — they're native DB columns.
            if ($def->backing === 'Column') {
                continue;
            }

            $defs[] = $def;
        }

        return $defs;
    }

    /**
     * Build per-attribute signature sets: scopable with non-empty axes get served signatures;
     * non-scopable or empty axes get an empty list (base-only).
     *
     * @param list<AttributeDefinition> $defs
     * @return array<string, list<ScopeSignature>> code => signatures
     */
    private function buildDefSignatures(array $defs): array
    {
        $defSignatures = [];

        foreach ($defs as $def) {
            if (!$def->scopable) {
                $defSignatures[$def->code] = [];
                continue;
            }

            $axes = $def->config()['axes'] ?? [];

            if ($axes === []) {
                $defSignatures[$def->code] = [];
                continue;
            }

            $defSignatures[$def->code] = $this->servedScopesProvider->signatures($axes);
        }

        return $defSignatures;
    }

    /**
     * Run scoped passes: group attributes by their signature set, run each distinct set once.
     *
     * @param array<int, Product>                $products
     * @param list<AttributeDefinition>          $indexedDefs
     * @param array<string, list<ScopeSignature>> $defSignatures
     * @param array<int, array<string, mixed>>   $baseValues
     * @param array<int, list<ProductAttributeIndexEntry>> $rowsByProduct
     */
    private function runScopedPasses(
        array $products,
        array $indexedDefs,
        array $defSignatures,
        array $baseValues,
        array &$rowsByProduct,
    ): void {
        // Group defs by their signature set (serialized key → list of defs).
        /** @var array<string, list<AttributeDefinition>> $groupedBySignatureSet */
        $groupedBySignatureSet = [];

        /** @var array<string, list<ScopeSignature>> $signaturesByKey */
        $signaturesByKey = [];

        foreach ($indexedDefs as $def) {
            $signatures = $defSignatures[$def->code] ?? [];

            if ($signatures === []) {
                continue;
            }

            // Use the serialized signature strings as a key to group by.
            $key = implode('|', array_map(
                fn (ScopeSignature $s): string => $s->toString(),
                $signatures,
            ));

            if (!isset($groupedBySignatureSet[$key])) {
                $groupedBySignatureSet[$key] = [];
                $signaturesByKey[$key] = $signatures;
            }

            $groupedBySignatureSet[$key][] = $def;
        }

        // For each distinct signature set, run the scoped passes.
        foreach ($groupedBySignatureSet as $key => $defs) {
            $signatures = $signaturesByKey[$key];

            $this->scopePassRunner->each($signatures, function (?ScopeSignature $sig) use (
                $products,
                $defs,
                $baseValues,
                &$rowsByProduct,
            ): void {
                if ($sig === null) {
                    // Base pass already handled above — skip.
                    return;
                }

                $sigStr = $sig->toString();

                foreach ($products as $productId => $product) {
                    foreach ($defs as $def) {
                        $value = $this->scopedProductAttributeAccessor->resolve($product, $def->code);

                        if ($value === null) {
                            continue;
                        }

                        // Skip-redundant: only emit if differs from the base value.
                        $baseValue = $baseValues[$productId][$def->code] ?? null;

                        if ($value === $baseValue) {
                            continue;
                        }

                        $newRows = $this->buildRows($productId, $def, $value, $sigStr);
                        $rowsByProduct[$productId] = array_merge($rowsByProduct[$productId], $newRows);
                    }
                }
            });
        }
    }

    /**
     * Build one or more ProductAttributeIndexEntry rows for a given resolved value.
     * Multiselect → one row per member; single-valued → one row.
     *
     * @return list<ProductAttributeIndexEntry>
     */
    private function buildRows(
        int $productId,
        AttributeDefinition $def,
        mixed $value,
        string $scopeSignature,
    ): array {
        // Multiselect: one row per array member.
        if ($def->type === 'multiselect' && is_array($value)) {
            return array_values(array_map(
                fn (mixed $member): ProductAttributeIndexEntry => $this->buildSingleRow(
                    $productId,
                    $def,
                    $member,
                    $scopeSignature,
                ),
                $value,
            ));
        }

        return [$this->buildSingleRow($productId, $def, $value, $scopeSignature)];
    }

    private function buildSingleRow(
        int $productId,
        AttributeDefinition $def,
        mixed $value,
        string $scopeSignature,
    ): ProductAttributeIndexEntry {
        $entry = new ProductAttributeIndexEntry();
        $entry->productId = $productId;
        $entry->attributeCode = $def->code;
        $entry->scopeSignature = $scopeSignature;
        $entry->valueKind = $def->type;

        match ($def->type) {
            'int', 'decimal' => $entry->valueNumber = (string) $value,
            'bool'           => $entry->valueBool = (bool) $value,
            default          => $entry->valueText = (string) $value,
        };

        return $entry;
    }
}
