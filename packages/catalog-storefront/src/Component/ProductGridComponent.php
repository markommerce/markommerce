<?php

declare(strict_types=1);

namespace Markommerce\CatalogStorefront\Component;

use Marko\Database\Exceptions\RepositoryException;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Exceptions\InvalidPaginationConfigException;
use Markommerce\Catalog\Exceptions\PageDepthExceededException;
use Markommerce\Catalog\Filtering\FilterSelection;
use Markommerce\Catalog\Pagination\PaginationOptionsResolver;
use Markommerce\Catalog\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Catalog\Pricing\Exceptions\PriceUnavailableException;
use Markommerce\Catalog\Pricing\PriceContext;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Sorting\CategorySortOrderRegistry;
use Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface;
use Markommerce\CatalogStorefront\Data\ProductGridData;
use Markommerce\Criteria\Contracts\RandomAccessPageInterface;
use Markommerce\Currency\CurrencyResolver;
use Markommerce\Layout\ExtensionBag;
use Markommerce\Money\Money;
use Markommerce\MoneyIntl\MoneyFormatter;

class ProductGridComponent
{
    public function __construct(
        private CategoryAssignmentService $categoryAssignmentService,
        private PaginationOptionsResolver $paginationOptionsResolver,
        private PriceResolverInterface $priceResolver,
        private MoneyFormatter $moneyFormatter,
        private ProductPriceIndexRepositoryInterface $productPriceIndexRepository,
        private CurrencyResolver $currencyResolver,
        private CategorySortOrderRegistry $categorySortOrderRegistry = new CategorySortOrderRegistry(),
    ) {}

    /**
     * Normalize the raw bracketed `filter[...]` query array into a FilterSelection.
     *
     * @param array<string, mixed> $filter
     */
    private function selectionFromFilter(array $filter): FilterSelection
    {
        $normalized = [];

        foreach ($filter as $key => $value) {
            $values = is_array($value)
                ? array_values(array_map(strval(...), $value))
                : [(string) $value];

            $normalized[(string) $key] = $values;
        }

        return new FilterSelection($normalized);
    }

    /**
     * @throws RepositoryException|InvalidPaginationConfigException|PageDepthExceededException
     */
    /**
     * @param array<string, mixed> $filter Raw bracketed `filter[...]` query array (parsed into a
     *                                      FilterSelection by the layered-navigation assembler).
     */
    public function data(
        Category $category,
        int $page,
        int $size,
        string $sort,
        array $filter = [],
    ): ProductGridData {
        $id = $category->id;

        if ($id === null) {
            return new ProductGridData(
                category: $category,
                products: [],
                resolvedNames: [],
                resolvedDescs: [],
                formattedPrices: [],
                extensions: new ExtensionBag(),
            );
        }

        $options = $this->paginationOptionsResolver->resolve(
            $page,
            $size > 0 ? $size : null,
            $sort !== '' ? $sort : null,
        );

        // Build the filter selection straight from the bracketed `filter[...]` query array and let the
        // catalog ProductListFilterRegistry apply it (catalog-attribute-storefront registers the
        // AttributeProductListFilter, which narrows by valid facetable attribute codes and ignores the
        // rest). This keeps catalog-storefront attribute-agnostic — no dependency on the assembler.
        $selection = $this->selectionFromFilter($filter);

        // Carried into the view so the sort/pagination forms can re-emit the active filters as hidden
        // inputs — otherwise changing the sort order would drop the selection.
        $appliedFilters = [];

        foreach ($selection->keys() as $filterKey) {
            $appliedFilters[$filterKey] = $selection->forKey($filterKey);
        }

        // Facets/active filters are rendered by the dedicated facet-sidebar component
        // (catalog-attribute-storefront); the grid only lists the (filtered) products.
        $facets = [];
        $activeFilters = [];

        $page = $this->categoryAssignmentService->paginatedProductsInCategory($id, $options, $selection);

        $products = array_values($page->items->toArray());

        $resolvedNames = [];
        $resolvedDescs = [];
        $formattedPrices = [];

        $productIds = array_values(array_filter(
            array_map(fn ($p) => $p->id, $products),
            fn ($id) => $id !== null,
        ));

        $indexEntries = $this->productPriceIndexRepository->findByProductIds($productIds);
        $baseCurrency = $this->currencyResolver->base();

        foreach ($products as $product) {
            if ($product->id === null) {
                continue;
            }

            $resolvedNames[$product->id] = $product->name;
            $resolvedDescs[$product->id] = $product->description;

            $entry = $indexEntries[$product->id] ?? null;

            if ($entry !== null && $entry->amount !== null) {
                $money = Money::of($entry->amount, $baseCurrency);
                $formattedPrices[$product->id] = $this->moneyFormatter->format($money);
            } else {
                try {
                    $money = $this->priceResolver->resolve(PriceContext::forProduct($product));
                    $formattedPrices[$product->id] = $this->moneyFormatter->format($money);
                } catch (PriceUnavailableException) {
                    $formattedPrices[$product->id] = null;
                }
            }
        }

        $currentPage = null;
        $totalPages = null;
        $pageLinkUrls = [];
        $nextPageUrl = null;
        $previousPageUrl = null;
        $canonicalPageUrl = null;

        if ($page instanceof RandomAccessPageInterface) {
            $currentPage = $page->currentPage();
            $totalPages = $page->totalPages();
            $pageLinkUrls = $this->buildPageLinkUrls(
                $page->totalPages(),
                $options->size,
                $sort,
                $size,
                $appliedFilters,
            );

            if ($currentPage < $totalPages) {
                $params = ['page' => $currentPage + 1];

                if ($size > 0) {
                    $params['size'] = $size;
                }

                if ($sort !== '') {
                    $params['sort'] = $sort;
                }

                if ($appliedFilters !== []) {
                    $params['filter'] = $appliedFilters;
                }

                // Load-more / infinite scroll fetch the chrome-less fragment
                // endpoint, NOT the full category page.
                $nextPageUrl = sprintf('/catalog/category/%d/page?%s', $id, http_build_query($params));
            }

            if ($currentPage > 1) {
                $params = ['page' => $currentPage - 1];

                if ($size > 0) {
                    $params['size'] = $size;
                }

                if ($sort !== '') {
                    $params['sort'] = $sort;
                }

                if ($appliedFilters !== []) {
                    $params['filter'] = $appliedFilters;
                }

                $previousPageUrl = sprintf('/catalog/category/%d/page?%s', $id, http_build_query($params));
            }

            // Page 1 canonicalizes to the bare category URL (no ?page=1).
            $canonicalParams = [];

            if ($currentPage > 1) {
                $canonicalParams['page'] = $currentPage;
            }

            if ($size > 0) {
                $canonicalParams['size'] = $size;
            }

            if ($sort !== '') {
                $canonicalParams['sort'] = $sort;
            }

            if ($appliedFilters !== []) {
                $canonicalParams['filter'] = $appliedFilters;
            }

            $canonicalPageUrl = $canonicalParams === []
                ? sprintf('/catalog/category/%d', $id)
                : sprintf('/catalog/category/%d?%s', $id, http_build_query($canonicalParams));
        } elseif ($page->nextPosition !== null) {
            $params = ['position' => $page->nextPosition];

            if ($size > 0) {
                $params['size'] = $size;
            }

            if ($sort !== '') {
                $params['sort'] = $sort;
            }

            if ($appliedFilters !== []) {
                $params['filter'] = $appliedFilters;
            }

            $nextPageUrl = sprintf('/catalog/category/%d/page?%s', $id, http_build_query($params));
        }

        $sortOptions = array_map(
            fn ($order) => ['key' => $order->key(), 'label' => $order->label()],
            $this->categorySortOrderRegistry->all(),
        );

        return new ProductGridData(
            category: $category,
            products: $products,
            resolvedNames: $resolvedNames,
            resolvedDescs: $resolvedDescs,
            formattedPrices: $formattedPrices,
            presentation: $options->presentation,
            currentPage: $currentPage,
            totalPages: $totalPages,
            hasNext: $page->hasNext(),
            hasPrevious: $page->hasPrevious(),
            pageLinkUrls: $pageLinkUrls,
            nextPageUrl: $nextPageUrl,
            previousPageUrl: $previousPageUrl,
            canonicalPageUrl: $canonicalPageUrl,
            sortOptions: $sortOptions,
            activeSort: $options->sortOrder->key(),
            extensions: new ExtensionBag(),
            facets: $facets,
            activeFilters: $activeFilters,
            appliedFilters: $appliedFilters,
        );
    }

    /**
     * Build crawlable page link URLs for numbered pagination.
     *
     * Preserves non-default size and sort query params, plus the active attribute filters.
     *
     * @param array<string, list<string>> $appliedFilters
     * @return list<string>
     */
    private function buildPageLinkUrls(
        int $totalPages,
        int $resolvedSize,
        string $sort,
        int $requestedSize,
        array $appliedFilters,
    ): array {
        $urls = [];

        for ($pageNum = 1; $pageNum <= $totalPages; $pageNum++) {
            $params = ['page' => $pageNum];

            if ($requestedSize > 0) {
                $params['size'] = $requestedSize;
            }

            if ($sort !== '') {
                $params['sort'] = $sort;
            }

            if ($appliedFilters !== []) {
                $params['filter'] = $appliedFilters;
            }

            $urls[] = '?' . http_build_query($params);
        }

        return $urls;
    }
}
