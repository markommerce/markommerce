<?php

declare(strict_types=1);

namespace Markommerce\CatalogStorefrontScope\Component;

use Marko\Core\Attributes\Preference;
use Marko\Database\Exceptions\RepositoryException;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\CatalogStorefront\Component\ProductGridComponent;
use Markommerce\CatalogStorefront\Data\ProductGridData;
use Markommerce\Scope\Exceptions\ScopeContextException;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Exceptions\UnknownScopeException;
use Markommerce\Scope\Resolver\ScopeResolver;

#[Preference(replaces: ProductGridComponent::class)]
class ScopedProductGridComponent extends ProductGridComponent
{
    public function __construct(
        CategoryAssignmentService $categoryAssignmentService,
        private ScopeResolver $scopeResolver,
    ) {
        parent::__construct($categoryAssignmentService);
    }

    /**
     * @throws RepositoryException|ScopeContextException|UnknownAxisException|UnknownScopeException
     */
    public function data(Category $category): ProductGridData
    {
        $data = parent::data($category);

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
            extensions: $data->extensions,
        );
    }
}
