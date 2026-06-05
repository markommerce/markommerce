<?php

declare(strict_types=1);

namespace Markommerce\CatalogStorefrontScope\Component;

use Marko\Core\Attributes\Preference;
use Marko\Database\Exceptions\RepositoryException;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Exceptions\InvalidPaginationConfigException;
use Markommerce\Catalog\Exceptions\PageDepthExceededException;
use Markommerce\Catalog\Pagination\PaginationOptionsResolver;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\CatalogStorefront\Component\ProductGridComponent;
use Markommerce\CatalogStorefront\Data\ProductGridData;
use Markommerce\MoneyIntl\MoneyFormatter;
use Markommerce\Pricing\Contracts\PriceResolverInterface;
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
    ) {
        parent::__construct($categoryAssignmentService, $paginationOptionsResolver, $priceResolver, $moneyFormatter);
    }

    /**
     * @throws RepositoryException|ScopeContextException|UnknownAxisException|UnknownScopeException
     * @throws InvalidPaginationConfigException|PageDepthExceededException
     */
    public function data(
        Category $category,
        int $page,
        int $size,
        string $sort,
    ): ProductGridData {
        $data = parent::data($category, $page, $size, $sort);

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
            extensions: $data->extensions,
        );
    }
}
