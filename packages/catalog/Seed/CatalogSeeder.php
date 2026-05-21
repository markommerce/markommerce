<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Seed;

use Marko\Database\Seed\Seeder;
use Marko\Database\Seed\SeederInterface;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductCategoryAssignmentRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Entity\ProductCategoryAssignment;
use Markommerce\Scope\Exceptions\ScopeStorageException;

#[Seeder(name: 'catalog')]
class CatalogSeeder implements SeederInterface
{
    private const int CATEGORY_COUNT = 5;

    private const int PRODUCT_COUNT = 30;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly ProductCategoryAssignmentRepositoryInterface $assignmentRepository,
    ) {}

    /**
     * @throws ScopeStorageException
     */
    public function run(): void
    {
        $categories = $this->seedCategories();
        $products = $this->seedProducts();
        $this->assignProductsToCategories($products, $categories);
    }

    /**
     * @return list<Category>
     * @throws ScopeStorageException
     */
    private function seedCategories(): array
    {
        $categories = [];

        for ($n = 1; $n <= self::CATEGORY_COUNT; $n++) {
            $category = new Category();
            $category->name = "Category {$n}";
            $category->description = "Description for category {$n}";
            $category->setOverride('locale:de', 'name', "Kategorie {$n}");
            $category->setOverride('locale:de', 'description', "Beschreibung für Kategorie {$n}");
            $category->setOverride('locale:fr', 'name', "Catégorie {$n}");
            $category->setOverride('locale:fr', 'description', "Description pour la catégorie {$n}");

            $this->categoryRepository->save($category);
            $categories[] = $category;
        }

        return $categories;
    }

    /**
     * @return list<Product>
     * @throws ScopeStorageException
     */
    private function seedProducts(): array
    {
        $products = [];

        for ($n = 1; $n <= self::PRODUCT_COUNT; $n++) {
            $product = new Product();
            $product->sku = 'SKU-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            $product->name = "Product {$n}";
            $product->description = "Description for product {$n}";
            $product->setOverride('locale:de', 'name', "Produkt {$n}");
            $product->setOverride('locale:de', 'description', "Beschreibung für Produkt {$n}");
            $product->setOverride('locale:fr', 'name', "Produit {$n}");
            $product->setOverride('locale:fr', 'description', "Description pour le produit {$n}");

            $this->productRepository->save($product);
            $products[] = $product;
        }

        return $products;
    }

    /**
     * @param list<Product> $products
     * @param list<Category> $categories
     */
    private function assignProductsToCategories(array $products, array $categories): void
    {
        $categoryCount = count($categories);

        foreach ($products as $index => $product) {
            $primaryCategoryIndex = $index % $categoryCount;
            $this->createAssignment($product, $categories[$primaryCategoryIndex]);

            if ($index % 2 === 0) {
                $secondaryCategoryIndex = ($primaryCategoryIndex + 1) % $categoryCount;
                $this->createAssignment($product, $categories[$secondaryCategoryIndex]);
            }
        }
    }

    private function createAssignment(Product $product, Category $category): void
    {
        $assignment = new ProductCategoryAssignment();
        $assignment->productId = $product->id;
        $assignment->categoryId = $category->id;

        $this->assignmentRepository->save($assignment);
    }
}
