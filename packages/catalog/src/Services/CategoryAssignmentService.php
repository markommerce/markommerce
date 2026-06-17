<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Services;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Exceptions\RepositoryException;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductCategoryAssignmentRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Entity\ProductCategoryAssignment;
use Markommerce\Catalog\Exceptions\CategoryNotFoundException;
use Markommerce\Catalog\Exceptions\ProductNotFoundException;
use Markommerce\Catalog\Filtering\FilterSelection;
use Markommerce\Catalog\Filtering\ProductListFilterRegistry;
use Markommerce\Catalog\Pagination\CategoryProductRowCounter;
use Markommerce\Catalog\Pagination\PaginationStrategyKind;
use Markommerce\Catalog\Pagination\ProductCursorValueExtractor;
use Markommerce\Catalog\Pagination\ResolvedPaginationOptions;
use Markommerce\Criteria\Exceptions\EmptySortException;
use Markommerce\Criteria\Exceptions\InvalidPageSizeException;
use Markommerce\Criteria\Exceptions\InvalidPositionTokenException;
use Markommerce\Criteria\Page\Page;
use Markommerce\Criteria\Page\PageRequest;
use Markommerce\Criteria\Position\OffsetPosition;
use Markommerce\Criteria\Position\PositionCodec;
use Markommerce\Criteria\Sort\Sort;
use Markommerce\Criteria\Strategy\KeysetPaginationStrategy;
use Markommerce\Criteria\Strategy\OffsetPaginationStrategy;

class CategoryAssignmentService
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private ProductCategoryAssignmentRepositoryInterface $productCategoryAssignmentRepository,
        private PositionCodec $positionCodec,
        private KeysetPaginationStrategy $keysetPaginationStrategy,
        private ConnectionInterface $connection,
        private ProductListFilterRegistry $filterRegistry = new ProductListFilterRegistry(),
    ) {}

    /**
     * @throws ProductNotFoundException|CategoryNotFoundException
     */
    public function assign(
        int $productId,
        int $categoryId,
    ): void {
        if ($this->productRepository->find($productId) === null) {
            throw ProductNotFoundException::forId($productId);
        }

        if ($this->categoryRepository->find($categoryId) === null) {
            throw CategoryNotFoundException::forId($categoryId);
        }

        $existing = $this->productCategoryAssignmentRepository->findByProductAndCategory($productId, $categoryId);

        if ($existing !== null) {
            return;
        }

        $assignment = new ProductCategoryAssignment();
        $assignment->productId = $productId;
        $assignment->categoryId = $categoryId;

        $this->productCategoryAssignmentRepository->save($assignment);
    }

    /**
     * @throws RepositoryException
     */
    public function detach(
        int $productId,
        int $categoryId,
    ): void {
        $existing = $this->productCategoryAssignmentRepository->findByProductAndCategory($productId, $categoryId);

        if ($existing === null) {
            return;
        }

        $this->productCategoryAssignmentRepository->delete($existing);
    }

    /**
     * @return list<Product>
     * @throws CategoryNotFoundException
     */
    public function productsInCategory(int $categoryId): array
    {
        if ($this->categoryRepository->find($categoryId) === null) {
            throw CategoryNotFoundException::forId($categoryId);
        }

        $assignments = $this->productCategoryAssignmentRepository->findByCategory($categoryId);

        $products = [];

        foreach ($assignments as $assignment) {
            $product = $this->productRepository->find($assignment->productId);

            if ($product === null) {
                continue;
            }

            $products[] = $product;
        }

        return $products;
    }

    /**
     * Return a paginated page of products assigned to the given category.
     *
     * Uses a single JOIN query against catalog_products + catalog_product_category,
     * avoiding the N+1 per-product lookups that productsInCategory() performs.
     *
     * Filter contributors are applied first (before sort-order), then sort-order
     * prepares the query, then pagination is executed.
     *
     * @return Page<Product>
     * @throws CategoryNotFoundException|RepositoryException
     */
    public function paginatedProductsInCategory(
        int $categoryId,
        ResolvedPaginationOptions $options,
        FilterSelection $filters = new FilterSelection(),
    ): Page {
        if ($this->categoryRepository->find($categoryId) === null) {
            throw CategoryNotFoundException::forId($categoryId);
        }

        $query = $this->productRepository->query()
            ->select(
                'catalog_products.id',
                'catalog_products.sku',
                'catalog_products.name',
                'catalog_products.description',
                'catalog_products.price_amount',
            )
            ->join('catalog_product_category', 'catalog_products.id', '=', 'catalog_product_category.product_id')
            ->where('catalog_product_category.category_id', '=', $categoryId);

        foreach ($this->filterRegistry->all() as $filter) {
            $filter->apply($query, $filters);
        }

        $options->sortOrder->prepareQuery($query);

        $pageRequest = $this->buildPageRequest($options);

        if ($options->strategyKind === PaginationStrategyKind::Keyset) {
            $extractor = new ProductCursorValueExtractor();

            /** @var Page<Product> */
            return $this->keysetPaginationStrategy->paginate($query, $pageRequest, $extractor);
        }

        // Offset strategy with a join-safe, filter-aware counter.
        // The query builder's own count() drops JOINs, so the counter compiles the fully-built
        // query (joins + where + filter EXISTS constraints) into a subquery and counts its rows.
        // This keeps the total correct whether or not attribute filters narrow the listing.
        $joinSafeCounter = new CategoryProductRowCounter($this->connection);

        $strategy = new OffsetPaginationStrategy($this->positionCodec, $joinSafeCounter);

        /** @var Page<Product> */
        return $strategy->paginate($query, $pageRequest);
    }

    /**
     * Build the PageRequest from ResolvedPaginationOptions.
     *
     * For page > 1 with offset strategy, encodes an OffsetPosition token.
     *
     * @throws InvalidPositionTokenException|InvalidPageSizeException|EmptySortException
     */
    private function buildPageRequest(ResolvedPaginationOptions $options): PageRequest
    {
        $sort = new Sort(...$options->sortOrder->sortFields());

        if ($options->page <= 1 || $options->strategyKind === PaginationStrategyKind::Keyset) {
            return PageRequest::first($options->size, $sort);
        }

        // For offset pagination on page > 1, encode the current page position
        $token = $this->positionCodec->encode(new OffsetPosition(page: $options->page));

        return PageRequest::at(
            $options->size,
            $sort,
            $token,
        );
    }
}
