<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Controller;

use Marko\Layout\Attributes\Layout;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\ThemeBlank\Layout\OneColumnLayout;

#[Layout(OneColumnLayout::class)]
class CategoryController
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
    ) {}

    #[Get('/catalog/category/{id}')]
    public function show(int $id): Response
    {
        $category = $this->categoryRepository->find($id);

        if ($category === null) {
            return Response::html('', 404);
        }

        return Response::html('', 200);
    }
}
