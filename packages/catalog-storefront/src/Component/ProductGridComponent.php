<?php

declare(strict_types=1);

namespace Markommerce\CatalogStorefront\Component;

use Marko\Database\Exceptions\RepositoryException;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Exceptions\InvalidPaginationConfigException;
use Markommerce\Catalog\Exceptions\PageDepthExceededException;
use Markommerce\Catalog\Pagination\PaginationOptionsResolver;
use Markommerce\Catalog\Pagination\PaginationPresentation;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\CatalogStorefront\Data\ProductGridData;
use Markommerce\Criteria\Contracts\RandomAccessPageInterface;
use Markommerce\Layout\ExtensionBag;
use Markommerce\MoneyIntl\MoneyFormatter;
use Markommerce\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Pricing\Exceptions\PriceUnavailableException;
use Markommerce\Pricing\PriceContext;

class ProductGridComponent
{
    public function __construct(
        private CategoryAssignmentService $categoryAssignmentService,
        private PaginationOptionsResolver $paginationOptionsResolver,
        private PriceResolverInterface $priceResolver,
        private MoneyFormatter $moneyFormatter,
    ) {}

    /**
     * @throws RepositoryException
     * @throws InvalidPaginationConfigException
     * @throws PageDepthExceededException
     */
    public function data(
        Category $category,
        int $page,
        int $size,
        string $sort,
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

        $page = $this->categoryAssignmentService->paginatedProductsInCategory($id, $options);
        $products = array_values($page->items->toArray());

        $resolvedNames = [];
        $resolvedDescs = [];
        $formattedPrices = [];

        foreach ($products as $product) {
            if ($product->id === null) {
                continue;
            }

            $resolvedNames[$product->id] = $product->name;
            $resolvedDescs[$product->id] = $product->description;

            try {
                $money = $this->priceResolver->resolve(PriceContext::forProduct($product));
                $formattedPrices[$product->id] = $this->moneyFormatter->format($money);
            } catch (PriceUnavailableException) {
                $formattedPrices[$product->id] = null;
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
                $options->pageRequest->size,
                $sort,
                $size,
            );

            if ($currentPage < $totalPages) {
                $params = ['page' => $currentPage + 1];

                if ($size > 0) {
                    $params['size'] = $size;
                }

                if ($sort !== '') {
                    $params['sort'] = $sort;
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

            $nextPageUrl = sprintf('/catalog/category/%d/page?%s', $id, http_build_query($params));
        }

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
            extensions: new ExtensionBag(),
        );
    }

    /**
     * Build crawlable page link URLs for numbered pagination.
     *
     * Preserves non-default size and sort query params.
     *
     * @return list<string>
     */
    private function buildPageLinkUrls(
        int $totalPages,
        int $resolvedSize,
        string $sort,
        int $requestedSize,
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

            $urls[] = '?' . http_build_query($params);
        }

        return $urls;
    }
}
