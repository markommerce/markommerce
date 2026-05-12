<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Service;

use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Exception\CategoryNotFoundException;
use Markommerce\Catalog\Exception\InvalidCategoryDataException;

interface CategoryServiceInterface
{
    /** @throws InvalidCategoryDataException */
    public function create(string $name): Category;

    public function get(int $id): ?Category;

    /** @return array<Category> */
    public function list(): array;

    /**
     * @throws InvalidCategoryDataException
     * @throws CategoryNotFoundException
     */
    public function update(Category $category): Category;

    /** @throws CategoryNotFoundException */
    public function delete(int $id): void;
}
