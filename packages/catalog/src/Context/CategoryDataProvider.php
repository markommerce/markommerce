<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Context;

use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Exceptions\CategoryNotFoundException;
use Markommerce\Layout\Contracts\ContextProvider;

class CategoryDataProvider implements ContextProvider
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
    ) {}

    /**
     * @param array<string, mixed> $props
     * @throws CategoryNotFoundException
     */
    public function provide(array $props): object
    {
        $id = (int) $props['id'];
        $category = $this->categoryRepository->find($id);

        if ($category === null) {
            throw CategoryNotFoundException::forId($id);
        }

        return $category;
    }
}
