<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Services;

use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeNodeRepositoryInterface;
use Markommerce\Catalog\Exceptions\CategoryHasPlacementsException;
use Markommerce\Catalog\Exceptions\CategoryNotFoundException;

class CategoryService
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
        private CategoryTreeNodeRepositoryInterface $categoryTreeNodeRepository,
    ) {}

    /**
     * @throws CategoryNotFoundException|CategoryHasPlacementsException
     */
    public function delete(int $categoryId): void
    {
        $category = $this->categoryRepository->find($categoryId);

        if ($category === null) {
            throw CategoryNotFoundException::forId($categoryId);
        }

        $placements = $this->categoryTreeNodeRepository->findByCategoryAcrossTrees($categoryId);

        if (count($placements) > 0) {
            throw CategoryHasPlacementsException::forCategory($categoryId, count($placements));
        }

        $this->categoryRepository->delete($category);
    }
}
