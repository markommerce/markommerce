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
use Markommerce\Indexer\Contracts\IndexerInterface;
use Markommerce\Indexer\ScopePassRunner;
use Markommerce\Scope\Signature\ScopeSignature;

class PriceIndexer implements PriceIndexerInterface, IndexerInterface
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private BatchPriceResolverInterface $batchPriceResolver,
        private ProductPriceIndexRepositoryInterface $indexRepository,
        private IndexedMarketsProviderInterface $indexedMarketsProvider,
        private ScopePassRunner $scopePassRunner,
    ) {}

    /**
     * @param list<int> $ids
     */
    public function reindex(array $ids): int
    {
        return $this->reindexProducts($ids);
    }

    public function reindexOne(int $id): int
    {
        return $this->reindexProduct($id);
    }

    /**
     * @param list<int> $ids
     */
    public function reindexProducts(array $ids): int
    {
        return $this->doReindexProducts($ids);
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

        /** @var array<int, ProductPriceIndexEntry> $entries */
        $entries = [];

        // Build one ScopeSignature per indexed market.
        $signatures = array_map(
            fn (string $market): ScopeSignature => new ScopeSignature(['market' => $market]),
            $this->indexedMarketsProvider->markets(),
        );

        $this->scopePassRunner->each(
            $signatures,
            function (?ScopeSignature $signature) use ($products, &$entries): void {
                $results = $this->batchPriceResolver->resolve($products);

                if ($signature === null) {
                    // Base pass — populate entries with base amount and currency.
                    foreach ($results as $productId => $money) {
                        $entry               = new ProductPriceIndexEntry();
                        $entry->productId    = (int) $productId;
                        $entry->amount       = $money->amount();
                        $entry->currencyCode = $money->currency()->code;
                        $entries[(int) $productId] = $entry;
                    }
                } else {
                    // Market pass — store per-market override using the key from the signature.
                    $market = $signature->get('market');

                    foreach ($results as $productId => $money) {
                        if (isset($entries[(int) $productId]) && $market !== null) {
                            $entries[(int) $productId]->setOverride("market:$market", 'amount', $money->amount());
                        }
                    }
                }
            },
        );

        if ($entries !== []) {
            $this->indexRepository->upsertMany(array_values($entries));
        }

        return count($entries);
    }
}
