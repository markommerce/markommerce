<?php

declare(strict_types=1);

namespace Markommerce\CatalogStorefrontScope\Component;

use Marko\Core\Attributes\Preference;
use Marko\Database\Exceptions\RepositoryException;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Exceptions\InvalidPaginationConfigException;
use Markommerce\Catalog\Exceptions\PageDepthExceededException;
use Markommerce\Catalog\Filtering\FilterSelection;
use Markommerce\Catalog\Pagination\PaginationOptionsResolver;
use Markommerce\Catalog\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\CatalogPriceIndex\Contracts\ProductPriceIndexRepositoryInterface;
use Markommerce\CatalogStorefront\Component\ProductGridComponent;
use Markommerce\CatalogStorefront\Contracts\LayeredNavigationAssemblerInterface;
use Markommerce\CatalogStorefront\Data\ProductGridData;
use Markommerce\Currency\CurrencyResolver;
use Markommerce\MoneyIntl\MoneyFormatter;
use Markommerce\Scope\Exceptions\ScopeContextException;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Exceptions\UnknownScopeException;
use Markommerce\Scope\Resolver\ScopeResolver;

#[Preference(replaces: ProductGridComponent::class)]
class ScopedProductGridComponent extends ProductGridComponent
{
    public function __construct(
        CategoryAssignmentService $categoryAssignmentService,
        PaginationOptionsResolver $paginationOptionsResolver,
        private ScopeResolver $scopeResolver,
        PriceResolverInterface $priceResolver,
        MoneyFormatter $moneyFormatter,
        ProductPriceIndexRepositoryInterface $productPriceIndexRepository,
        CurrencyResolver $currencyResolver,
        ?LayeredNavigationAssemblerInterface $layeredNavigationAssembler = null,
    ) {
        parent::__construct(
            $categoryAssignmentService,
            $paginationOptionsResolver,
            $priceResolver,
            $moneyFormatter,
            $productPriceIndexRepository,
            $currencyResolver,
            layeredNavigationAssembler: $layeredNavigationAssembler,
        );
    }

    /**
     * @throws RepositoryException|ScopeContextException|UnknownAxisException|UnknownScopeException|InvalidPaginationConfigException|PageDepthExceededException
     */
    public function data(
        Category $category,
        int $page,
        int $size,
        string $sort,
        FilterSelection $selection = new FilterSelection(),
    ): ProductGridData {
        $data = parent::data($category, $page, $size, $sort, $selection);

        $resolvedNames = $data->resolvedNames;
        $resolvedDescs = $data->resolvedDescs;

        foreach ($data->products as $product) {
            if ($product->id === null) {
                continue;
            }

            $resolvedNames[$product->id] = $this->scopeResolver->resolved($product, 'name');
            $resolvedDescs[$product->id] = $this->scopeResolver->resolved($product, 'description');
        }

        return new ProductGridData(
            category: $data->category,
            products: $data->products,
            resolvedNames: $resolvedNames,
            resolvedDescs: $resolvedDescs,
            formattedPrices: $data->formattedPrices,
            presentation: $data->presentation,
            currentPage: $data->currentPage,
            totalPages: $data->totalPages,
            hasNext: $data->hasNext,
            hasPrevious: $data->hasPrevious,
            pageLinkUrls: $data->pageLinkUrls,
            nextPageUrl: $data->nextPageUrl,
            previousPageUrl: $data->previousPageUrl,
            canonicalPageUrl: $data->canonicalPageUrl,
            sortOptions: $data->sortOptions,
            activeSort: $data->activeSort,
            extensions: $data->extensions,
            facets: $data->facets,
            activeFilters: $data->activeFilters,
        );
    }
}
