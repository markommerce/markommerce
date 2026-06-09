<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Tests\Support;

use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Testing\Fixtures\FixtureFactory;
use Markommerce\Testing\Profile\BootedStore;

class CategoryFactory extends FixtureFactory
{
    private string $name = '';

    private function __construct(BootedStore $store)
    {
        parent::__construct($store);
    }

    public static function new(BootedStore $store): self
    {
        return new self($store);
    }

    public function withName(string $name): self
    {
        $clone = clone $this;
        $clone->name = $name;

        return $clone;
    }

    public function create(): Category
    {
        $category = new Category();
        $category->name = $this->name !== '' ? $this->name : 'Category ' . self::nextCounter();

        /** @var CategoryRepositoryInterface $repo */
        $repo = $this->store->get(CategoryRepositoryInterface::class);
        $repo->save($category);

        return $category;
    }
}
