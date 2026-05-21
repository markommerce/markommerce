<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Controller;

use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Exceptions\CategoryNotFoundException;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Scope\Resolver\ScopeResolver;

class CategoryController
{
    public function __construct(
        private CategoryAssignmentService $categoryAssignmentService,
        private CategoryRepositoryInterface $categoryRepository,
        private ScopeResolver $scopeResolver,
        private ViewInterface $view,
    ) {}

    /**
     * @throws \Marko\Database\Exceptions\RepositoryException
     */
    #[Get('/catalog/category/{id}')]
    public function show(int $id): Response
    {
        $category = $this->categoryRepository->find($id);

        if ($category === null) {
            return Response::html('', 404);
        }

        try {
            $products = $this->categoryAssignmentService->productsInCategory($id);
        } catch (CategoryNotFoundException) {
            return Response::html('', 404);
        }

        $resolvedNames = [];
        foreach ($products as $product) {
            $resolvedNames[] = $this->scopeResolver->resolved($product, 'name');
        }

        return $this->view->render('catalog::category', [
            'category' => $category,
            'products' => $products,
            'resolvedNames' => $resolvedNames,
        ]);
    }
}
