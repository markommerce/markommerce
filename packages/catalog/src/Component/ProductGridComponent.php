<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Component;

use Marko\Database\Exceptions\RepositoryException;
use Marko\Layout\Attributes\Component;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Controller\CategoryController;
use Markommerce\Catalog\Exceptions\CategoryNotFoundException;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Scope\Resolver\ScopeResolver;

#[Component(template: 'catalog::components/product-grid', handle: [CategoryController::class, 'show'], slot: 'content')]
class ProductGridComponent
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
        private CategoryAssignmentService $categoryAssignmentService,
        private ScopeResolver $scopeResolver,
    ) {}

    /**
     * @return array<string, mixed>
     * @throws CategoryNotFoundException|RepositoryException
     */
    public function data(int $id): array
    {
        $category = $this->categoryRepository->find($id);

        if ($category === null) {
            throw CategoryNotFoundException::forId($id);
        }

        $products = $this->categoryAssignmentService->productsInCategory($id);

        $resolvedNames = [];
        $resolvedDescs = [];

        foreach ($products as $product) {
            if ($product->id === null) {
                continue;
            }

            $resolvedNames[$product->id] = $this->scopeResolver->resolved($product, 'name');
            $resolvedDescs[$product->id] = $this->scopeResolver->resolved($product, 'description');
        }

        return [
            'category' => $category,
            'products' => $products,
            'resolvedNames' => $resolvedNames,
            'resolvedDescs' => $resolvedDescs,
        ];
    }
}
