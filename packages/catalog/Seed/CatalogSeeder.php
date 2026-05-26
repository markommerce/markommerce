<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Seed;

use Marko\Database\Seed\Seeder;
use Marko\Database\Seed\SeederInterface;
use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Contracts\CategoryTreeNodeRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductCategoryAssignmentRepositoryInterface;
use Markommerce\Catalog\Contracts\ProductRepositoryInterface;
use Markommerce\Catalog\Entity\Category;
use Markommerce\Catalog\Entity\CategoryTree;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Entity\ProductCategoryAssignment;
use Markommerce\Catalog\Services\CategoryTreeService;
use Markommerce\Scope\Exceptions\ScopeStorageException;

#[Seeder(name: 'catalog')]
class CatalogSeeder implements SeederInterface
{
    private const int CATEGORY_COUNT = 5;

    private const int PRODUCTS_PER_CATEGORY = 1000;

    private const int LOCALE_OVERRIDE_PROBABILITY_PERCENT = 10;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly ProductCategoryAssignmentRepositoryInterface $assignmentRepository,
        private readonly CategoryTreeService $categoryTreeService,
        private readonly CategoryTreeNodeRepositoryInterface $categoryTreeNodeRepository,
    ) {}

    /**
     * @throws ScopeStorageException
     */
    public function run(): void
    {
        $categories = $this->seedCategories();
        $this->seedProductsAndAssignments($categories);
        $defaultTree = $this->categoryTreeService->ensureDefaultTreeExists();
        $this->placeCategoriesInDefaultTree($categories, $defaultTree);
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
            $category->name = "Category $n";
            $category->description = "Description for category $n";
            $category->setOverride('locale:de', 'name', "Kategorie $n");
            $category->setOverride('locale:de', 'description', "Beschreibung für Kategorie $n");
            $category->setOverride('locale:fr', 'name', "Catégorie $n");
            $category->setOverride('locale:fr', 'description', "Description pour la catégorie $n");

            $this->categoryRepository->save($category);
            $categories[] = $category;
        }

        return $categories;
    }

    /**
     * Inserts all products via insertBatch, then re-fetches them to get their IDs,
     * and bulk-inserts all assignments.
     *
     * @param list<Category> $categories
     * @throws ScopeStorageException
     */
    private function seedProductsAndAssignments(array $categories): void
    {
        $totalProducts = self::CATEGORY_COUNT * self::PRODUCTS_PER_CATEGORY;
        $entities = [];

        for ($n = 1; $n <= $totalProducts; $n++) {
            $product = new Product();
            $product->sku = 'SKU-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
            $product->name = "Product $n";
            $product->description = "Description for product $n";
            if ($this->shouldAddOverride()) {
                $product->setOverride('locale:de', 'name', "Produkt $n");
                $product->setOverride('locale:de', 'description', "Beschreibung für Produkt $n");
            }

            if ($this->shouldAddOverride()) {
                $product->setOverride('locale:fr', 'name', "Produit $n");
                $product->setOverride('locale:fr', 'description', "Description pour le produit $n");
            }

            $entities[] = $product;
        }

        $this->productRepository->insertBatch($entities);

        // Re-fetch to obtain auto-assigned IDs, then map by SKU
        $allProducts = $this->productRepository->findAll()->toArray();
        $productsBySku = [];

        foreach ($allProducts as $product) {
            /** @var Product $product */
            $productsBySku[$product->sku] = $product;
        }

        $categoryCount = count($categories);
        $assignments = [];

        for ($n = 1; $n <= $totalProducts; $n++) {
            $sku = 'SKU-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
            $product = $productsBySku[$sku];
            $primaryCategoryIndex = ($n - 1) % $categoryCount;

            $assignment = new ProductCategoryAssignment();
            $assignment->productId = $product->id;
            $assignment->categoryId = $categories[$primaryCategoryIndex]->id;
            $assignments[] = $assignment;
        }

        $this->assignmentRepository->insertBatch($assignments);
    }

    /**
     * @param list<Category> $categories
     */
    private function placeCategoriesInDefaultTree(array $categories, CategoryTree $defaultTree): void
    {
        $treeId = (int) $defaultTree->id;

        foreach ($categories as $category) {
            $categoryId = (int) $category->id;
            $existing = $this->categoryTreeNodeRepository->findByCategoryInTree($categoryId, $treeId);

            if (count($existing) === 0) {
                $this->categoryTreeService->placeCategory($treeId, $categoryId, parentNodeId: null, position: null);
            }
        }
    }

    private function shouldAddOverride(): bool
    {
        return random_int(1, 100) <= self::LOCALE_OVERRIDE_PROBABILITY_PERCENT;
    }
}
