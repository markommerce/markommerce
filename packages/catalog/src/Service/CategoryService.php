<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Service;

use Marko\Core\Event\EventDispatcherInterface;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Event\CategoryCreated;
use Markommerce\Catalog\Event\CategoryDeleted;
use Markommerce\Catalog\Event\CategoryUpdated;
use Markommerce\Catalog\Exception\CategoryNotFoundException;
use Markommerce\Catalog\Exception\InvalidCategoryDataException;
use Markommerce\Catalog\Repository\CategoryRepositoryInterface;

class CategoryService implements CategoryServiceInterface
{
    /**
     * @param CategoryRepositoryInterface<Category> $categoryRepository
     */
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    /**
     * @throws InvalidCategoryDataException
     */
    public function create(string $name): Category
    {
        if ($name === '') {
            throw InvalidCategoryDataException::emptyName();
        }

        $category = new Category();
        $category->name = $name;
        $this->categoryRepository->save($category);

        $this->eventDispatcher?->dispatch(new CategoryCreated($category));

        return $category;
    }

    public function get(int $id): ?Category
    {
        return $this->categoryRepository->find($id);
    }

    /** @return array<Category> */
    public function list(): array
    {
        return $this->categoryRepository->findAll()->toArray();
    }

    /**
     * @throws InvalidCategoryDataException
     * @throws CategoryNotFoundException
     */
    public function update(Category $category): Category
    {
        if ($category->name === '') {
            throw InvalidCategoryDataException::emptyName();
        }

        $this->categoryRepository->save($category);

        $this->eventDispatcher?->dispatch(new CategoryUpdated($category));

        return $category;
    }

    /** @throws CategoryNotFoundException */
    public function delete(int $id): void
    {
        $category = $this->categoryRepository->find($id);

        if ($category === null) {
            throw CategoryNotFoundException::forId($id);
        }

        $this->categoryRepository->delete($category);

        $this->eventDispatcher?->dispatch(new CategoryDeleted($id));
    }
}
