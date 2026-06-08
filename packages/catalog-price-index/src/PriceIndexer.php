<?php

declare(strict_types=1);

namespace Markommerce\CatalogPriceIndex;

use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Pricing\Contracts\BatchPriceResolverInterface;
use Markommerce\CatalogPriceIndex\Contracts\IndexedMarketsProviderInterface;
use Markommerce\CatalogPriceIndex\Contracts\PriceIndexerInterface;
use Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface;
use Markommerce\CatalogPriceIndex\Entity\ProductPriceIndexEntry;
use Markommerce\Scope\Context\ScopeContext;

class PriceIndexer implements PriceIndexerInterface
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private BatchPriceResolverInterface $batchPriceResolver,
        private ProductPriceIndexRepositoryInterface $indexRepository,
        private IndexedMarketsProviderInterface $indexedMarketsProvider,
        private ScopeContext $scopeContext,
    ) {}

    /**
     * @param list<int> $ids
     */
    public function reindexProducts(array $ids): int
    {
        $previousMarket = $this->scopeContext->get('market');

        try {
            return $this->doReindexProducts($ids);
        } finally {
            if ($previousMarket !== null) {
                $this->scopeContext->in('market', $previousMarket);
            } else {
                $this->scopeContext->clear('market');
            }
        }
    }

    public function reindexProduct(int $id): int
    {
        return $this->reindexProducts([$id]);
    }

    /** @param positive-int $chunkSize */
    public function rebuildAll(int $chunkSize = 500): int
    {
        $this->indexRepository->truncate();

        // One full-table id scan — O(1) statements regardless of product count.
        $rows   = $this->productRepository->query()->selectRaw('id')->get();
        $allIds = array_map(fn (array $row): int => (int) $row['id'], $rows);

        $total = 0;

        foreach (array_chunk($allIds, $chunkSize) as $chunkIds) {
            $total += $this->reindexProducts($chunkIds);
        }

        return $total;
    }

    /**
     * @param list<int> $ids
     */
    private function doReindexProducts(array $ids): int
    {
        // One product load per chunk — companions hydrated from the same row.
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

        // Base pass: clear market so no scoped override is applied.
        $this->scopeContext->clear('market');
        $baseResults = $this->batchPriceResolver->resolve($products);

        /** @var array<int, ProductPriceIndexEntry> $entries */
        $entries = [];

        foreach ($baseResults as $productId => $money) {
            $entry             = new ProductPriceIndexEntry();
            $entry->productId  = (int) $productId;
            $entry->amount     = $money->amount();
            $entry->currencyCode = $money->currency()->code;
            $entries[(int) $productId] = $entry;
        }

        // Per-market passes: one set-wise pipeline run per market.
        foreach ($this->indexedMarketsProvider->markets() as $market) {
            $this->scopeContext->in('market', $market);
            $marketResults = $this->batchPriceResolver->resolve($products);

            foreach ($marketResults as $productId => $money) {
                if (isset($entries[(int) $productId])) {
                    $entries[(int) $productId]->setOverride("market:$market", 'amount', $money->amount());
                }
            }

            $this->scopeContext->clear('market');
        }

        if ($entries !== []) {
            $this->indexRepository->upsertMany(array_values($entries));
        }

        return count($entries);
    }
}
