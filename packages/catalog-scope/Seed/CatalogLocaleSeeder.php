<?php

declare(strict_types=1);

namespace Markommerce\CatalogScope\Seed;

use Marko\Database\Seed\Seeder;
use Marko\Database\Seed\SeederInterface;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\CatalogScope\Entity\CategoryScopedOverrides;
use Markommerce\CatalogScope\Entity\ProductScopedOverrides;
use Markommerce\Scope\Exceptions\ScopeStorageException;

#[Seeder(name: 'catalog-locale', order: 10)]
class CatalogLocaleSeeder implements SeederInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
    ) {}

    /**
     * @throws ScopeStorageException
     */
    public function run(): void
    {
        $this->attachProductLocaleOverrides();
        $this->attachCategoryLocaleOverrides();
    }

    /**
     * @throws ScopeStorageException
     */
    private function attachProductLocaleOverrides(): void
    {
        $products = $this->productRepository->findAll();

        foreach ($products as $product) {
            /** @var Product $product */
            $n = $this->extractSequenceNumber($product->name, 'Product');

            if ($n === null) {
                continue;
            }

            $overrides = new ProductScopedOverrides();
            $overrides->setOverride('locale:de', 'name', "Produkt $n");
            $overrides->setOverride('locale:de', 'description', "Beschreibung für Produkt $n");
            $overrides->setOverride('locale:fr', 'name', "Produit $n");
            $overrides->setOverride('locale:fr', 'description', "Description pour le produit $n");

            $product->attachCompanion($overrides);
            $this->productRepository->save($product);
        }
    }

    /**
     * @throws ScopeStorageException
     */
    private function attachCategoryLocaleOverrides(): void
    {
        $categories = $this->categoryRepository->findAll();

        foreach ($categories as $category) {
            /** @var Category $category */
            $n = $this->extractSequenceNumber($category->name, 'Category');

            if ($n === null) {
                continue;
            }

            $overrides = new CategoryScopedOverrides();
            $overrides->setOverride('locale:de', 'name', "Kategorie $n");
            $overrides->setOverride('locale:de', 'description', "Beschreibung für Kategorie $n");
            $overrides->setOverride('locale:fr', 'name', "Catégorie $n");
            $overrides->setOverride('locale:fr', 'description', "Description pour la catégorie $n");

            $category->attachCompanion($overrides);
            $this->categoryRepository->save($category);
        }
    }

    private function extractSequenceNumber(string $name, string $prefix): ?int
    {
        if (preg_match('/^' . preg_quote($prefix, '/') . ' (\d+)$/', $name, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
