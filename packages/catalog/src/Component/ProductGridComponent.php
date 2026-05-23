<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Component;

use Marko\Database\Exceptions\RepositoryException;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Data\ProductGridData;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Layout\ExtensionBag;
use Markommerce\Scope\Resolver\ScopeResolver;

class ProductGridComponent
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
        private CategoryAssignmentService $categoryAssignmentService,
        private ScopeResolver $scopeResolver,
    ) {}

    /**
     * @throws RepositoryException
     */
    public function data(Category $category): ProductGridData
    {
        $id = $category->id;
        $products = $id !== null
            ? $this->categoryAssignmentService->productsInCategory($id)
            : [];

        $resolvedNames = [];
        $resolvedDescs = [];

        foreach ($products as $product) {
            if ($product->id === null) {
                continue;
            }

            $resolvedNames[$product->id] = $this->scopeResolver->resolved($product, 'name');
            $resolvedDescs[$product->id] = $this->scopeResolver->resolved($product, 'description');
        }

        return new ProductGridData(
            category: $category,
            products: $products,
            resolvedNames: $resolvedNames,
            resolvedDescs: $resolvedDescs,
            extensions: new ExtensionBag(),
        );
    }
}
