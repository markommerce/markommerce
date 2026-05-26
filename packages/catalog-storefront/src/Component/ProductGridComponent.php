<?php

declare(strict_types=1);

namespace Markommerce\CatalogStorefront\Component;

use Marko\Database\Exceptions\RepositoryException;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\CatalogStorefront\Data\ProductGridData;
use Markommerce\Layout\ExtensionBag;

class ProductGridComponent
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
        private CategoryAssignmentService $categoryAssignmentService,
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

            $resolvedNames[$product->id] = $product->name;
            $resolvedDescs[$product->id] = $product->description;
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
