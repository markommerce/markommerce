---
title: markommerce/catalog
description: Products and categories for Markommerce — globally-unique SKUs, per-market category trees, and a headless domain layer.
---

Products and categories for Markommerce. `markommerce/catalog` provides the `Product` and `Category` entities, repository interfaces, services, and a database seeder. Products carry a globally-unique SKU; categories carry a name and optional description. Category placement in navigation is managed separately through category trees --- each market can have its own tree or fall back to a shared default, and the same category may appear at multiple positions within a tree. Scope support is not built into `markommerce/catalog` --- the package ships with no scope dependency. To add locale-scoped overrides to catalog entities, install [markommerce/catalog-scope](/docs/packages/catalog-scope/) (storage layer) and [markommerce/catalog-locale](/docs/packages/catalog-locale/) (field registration bridge).

## Installation

```bash
composer require markommerce/catalog
```

The package declares itself as a `marko-module` and registers its repository bindings automatically via `module.php`. No manual service binding is required.

## Usage

### Creating a product

Use `ProductService::createProduct()` to create a product with duplicate-SKU protection. The service checks for an existing SKU before persisting and throws `DuplicateSkuException` if one is found:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Services\ProductService;
use Markommerce\Catalog\Exceptions\DuplicateSkuException;

try {
    $product = $productService->createProduct('SKU-0001', 'Widget', 'A handy widget.');
} catch (DuplicateSkuException $e) {
    // A product with this SKU already exists
}
```

To add locale-scoped overrides, install [markommerce/catalog-scope](/docs/packages/catalog-scope/) and [markommerce/catalog-locale](/docs/packages/catalog-locale/).

### Creating a category

Instantiate `Category` directly and save it via `CategoryRepositoryInterface`:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Contracts\CategoryRepositoryInterface;
use Markommerce\Catalog\Entity\Category;

$category = new Category();
$category->name = 'Widgets';
$category->description = 'All widget products.';
$categoryRepository->save($category);
```

### Assigning products to categories

Use `CategoryAssignmentService` to assign and detach products. Assigning a product that is already in the category is a no-op:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Catalog\Exceptions\ProductNotFoundException;
use Markommerce\Catalog\Exceptions\CategoryNotFoundException;

// Assign
$categoryAssignmentService->assign($product->id, $category->id);

// Detach
$categoryAssignmentService->detach($product->id, $category->id);

// List products in a category (all at once, N+1 per-product lookup)
$products = $categoryAssignmentService->productsInCategory($category->id);
```

Both `assign()` and `productsInCategory()` throw `ProductNotFoundException` or `CategoryNotFoundException` when the referenced entity does not exist.

### Paginated product listing

For paginated output use `CategoryAssignmentService::paginatedProductsInCategory()`. It executes a single JOIN query against `catalog_products` and `catalog_product_category`, avoiding the per-product N+1 lookups of `productsInCategory()`. It accepts a `ResolvedPaginationOptions` value object produced by `PaginationOptionsResolver`:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Pagination\PaginationOptionsResolver;
use Markommerce\Catalog\Services\CategoryAssignmentService;
use Markommerce\Criteria\Contracts\RandomAccessPageInterface;

// Resolve options from request parameters (null = use configured defaults).
$options = $paginationOptionsResolver->resolve(page: 1, size: null, sort: null);

$page = $categoryAssignmentService->paginatedProductsInCategory($category->id, $options);

foreach ($page->items as $product) { /* ... */ }

// With offset strategy the page implements RandomAccessPageInterface.
if ($page instanceof RandomAccessPageInterface) {
    echo $page->totalItems();  // total matching products
    echo $page->totalPages();
}
```

`PaginationOptionsResolver::resolve()` reads all values from `CatalogPaginationConfig` via the config system and validates the combination of strategy and presentation. It throws `InvalidPaginationConfigException` for invalid config values and `PageDepthExceededException` when the requested page number exceeds `maxPageDepth`.

### Pagination configuration

Pagination defaults are controlled through the `catalog/pagination` config scope. Publish or create `config/catalog/pagination.php` in your application to override any value:

```php title="config/catalog/pagination.php"
<?php

declare(strict_types=1);

return [
    'defaultPageSize'  => 24,
    'allowedPageSizes' => [12, 24, 48, 96],
    'maxPageSize'      => 96,
    'strategy'         => 'offset',    // 'offset' | 'keyset'
    'presentation'     => 'numbered',  // 'numbered' | 'load_more' | 'infinite'
    'countMode'        => 'exact',     // 'exact' | 'estimated'
    'maxPageDepth'     => 100,
    'defaultSort'      => 'position',
    'enabledSorts'     => [],          // empty = all registered sort orders are available
    'viewAllThreshold' => 0,           // 0 = disabled; N = show "view all" when total <= N
    'countCacheTtl'    => 0,           // reserved; set to 0
];
```

| Key | Default | Description |
|---|---|---|
| `defaultPageSize` | `24` | Page size used when no `size` parameter is supplied |
| `allowedPageSizes` | `[12, 24, 48, 96]` | Page sizes accepted from requests; requests with unlisted sizes fall back to `defaultPageSize` |
| `maxPageSize` | `96` | Hard upper bound; requests exceeding this fall back to `defaultPageSize` |
| `strategy` | `offset` | Pagination engine: `offset` (random access, numbered pages) or `keyset` (cursor-based, sequential) |
| `presentation` | `numbered` | Storefront UI mode: `numbered`, `load_more`, or `infinite`. `numbered` requires `strategy=offset` |
| `countMode` | `exact` | How total rows are counted: `exact` (SELECT COUNT) or `estimated` (planner estimate with exact fallback) |
| `maxPageDepth` | `100` | Requests for page numbers above this return a `410 Gone` response |
| `defaultSort` | `position` | Sort order key used when no `sort` parameter is supplied; must be a registered key in `CategorySortOrderRegistry` |
| `enabledSorts` | `[]` | Allowlist of sort-order keys accepted from requests. An empty array (the default) exposes every order registered in `CategorySortOrderRegistry`. Set to a non-empty list to restrict which orders the storefront exposes. |
| `viewAllThreshold` | `0` | When `> 0`, categories with at most this many products expose a `?view=all` URL and the canonical points there |
| `countCacheTtl` | `0` | Reserved for future use; leave as `0` |

**Breaking change from `allowedSorts`:** The config key was renamed from `allowedSorts` (key `catalog/pagination.allowedSorts`) to `enabledSorts` (key `catalog/pagination.enabledSorts`). Applications that override this key must rename it. The semantics also changed: the default is now `[]` (expose all registered orders), whereas the old default was an explicit list of column tokens.

**Constraint:** `presentation=numbered` requires `strategy=offset`. Setting `numbered` with `strategy=keyset` throws `InvalidPaginationConfigException`.

### Category sort orders

Sort orders for the category product listing are managed through `CategorySortOrderRegistry`. Any package can register additional sort orders at boot time; the storefront dropdown is populated from the registry at render time.

`markommerce/catalog` registers the `position` sort order by default. [markommerce/catalog-price-index](/docs/packages/catalog-price-index/) registers `price_asc` and `price_desc` when installed.

#### Implementing a custom sort order

Implement `CategorySortOrderInterface` and register the instance in your `module.php` boot closure:

```php
<?php

declare(strict_types=1);

use Marko\Database\Repository\RepositoryQueryBuilder;
use Markommerce\Catalog\Sorting\CategorySortOrderInterface;
use Markommerce\Criteria\Sort\SortDirection;
use Markommerce\Criteria\Sort\SortField;

class NameSortOrder implements CategorySortOrderInterface
{
    public function key(): string
    {
        return 'name';
    }

    public function label(): string
    {
        return 'Name';
    }

    public function supportsKeyset(): bool
    {
        return true;
    }

    public function prepareQuery(RepositoryQueryBuilder $repositoryQueryBuilder): void
    {
        // No additional JOINs needed for a plain column sort.
    }

    /** @return list<SortField> */
    public function sortFields(): array
    {
        return [new SortField('catalog_products.name', SortDirection::Ascending)];
    }
}
```

Register it in `module.php`:

```php title="module.php"
<?php

declare(strict_types=1);

use Markommerce\Catalog\Sorting\CategorySortOrderRegistry;

return [
    'boot' => function (CategorySortOrderRegistry $categorySortOrderRegistry, NameSortOrder $nameSortOrder): void {
        $categorySortOrderRegistry->register($nameSortOrder, priority: 10);
    },
];
```

Pass the implementation class (not a `new` expression) to the closure so the container can resolve it and honour any `#[Preference]` overrides registered by other packages.

#### Using `ColumnSortOrder` for simple column sorts

For sorts that map directly to a single database column without extra JOINs, use the built-in `ColumnSortOrder` helper instead of writing a full class:

```php title="module.php"
<?php

declare(strict_types=1);

use Markommerce\Catalog\Sorting\CategorySortOrderRegistry;
use Markommerce\Catalog\Sorting\ColumnSortOrder;
use Markommerce\Criteria\Sort\SortDirection;

return [
    'boot' => function (CategorySortOrderRegistry $categorySortOrderRegistry): void {
        $categorySortOrderRegistry->register(new ColumnSortOrder(
            key: 'sku',
            label: 'SKU',
            column: 'catalog_products.sku',
            direction: SortDirection::Ascending,
            supportsKeyset: true,
        ), priority: 20);
    },
];
```

### Working with category trees

Category trees separate a category's identity from its position in navigation. A tree is identified by a unique `code` and can be marked as the default tree. Markets can be assigned to a specific tree; any market without an explicit assignment uses the default tree.

#### Creating and configuring trees

Use `CategoryTreeService` to create trees and manage the default:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Contracts\CategoryTreeServiceInterface;
use Markommerce\Catalog\Exceptions\DuplicateDefaultTreeException;

// Create a non-default tree
$euTree = $categoryTreeService->createTree(code: 'eu', name: 'European Catalogue');

// Create the default tree (exactly one tree may be default)
try {
    $defaultTree = $categoryTreeService->createTree(code: 'default', name: 'Default', isDefault: true);
} catch (DuplicateDefaultTreeException $e) {
    // A default tree already exists
}

// Promote an existing tree to default (demotes the current default automatically)
$categoryTreeService->setDefaultTree($euTree->id);

// Idempotent bootstrap helper — returns the existing default or creates one
$defaultTree = $categoryTreeService->ensureDefaultTreeExists();
```

#### Assigning trees to markets

Market-to-tree assignment and resolution is provided by [markommerce/catalog-market](/docs/packages/catalog-market/). Install that package to use `CategoryTreeMarketAssignmentService` and `CategoryTreeMarketResolver`.

#### Placing categories in a tree

A category can be placed at multiple positions within the same tree. Pass `null` for `$parentNodeId` to create a root-level placement; omit `$position` to append after existing siblings with an automatic gap of 10:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Contracts\CategoryTreeServiceInterface;

// Place a category at the root of the default tree
$rootNode = $categoryTreeService->placeCategory(
    treeId: $defaultTree->id,
    categoryId: $category->id,
);

// Place a category under an existing node
$childNode = $categoryTreeService->placeCategory(
    treeId: $defaultTree->id,
    categoryId: $subCategory->id,
    parentNodeId: $rootNode->id,
);

// Place at an explicit position
$categoryTreeService->placeCategory(
    treeId: $defaultTree->id,
    categoryId: $featuredCategory->id,
    parentNodeId: null,
    position: 0,
);
```

#### Moving and reordering nodes

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Contracts\CategoryTreeServiceInterface;
use Markommerce\Catalog\Exceptions\CircularNodeReferenceException;

// Move a node to a new parent at a given position (cycle detection included)
try {
    $categoryTreeService->moveNode(nodeId: $childNode->id, newParentNodeId: $otherNode->id, position: 10);
} catch (CircularNodeReferenceException $e) {
    // The proposed parent is a descendant of the node
}

// Reorder all siblings under a parent by passing an ordered list of node IDs
$categoryTreeService->reorderSiblings(
    parentNodeId: $rootNode->id,
    treeId: $defaultTree->id,
    orderedNodeIds: [$nodeC->id, $nodeA->id, $nodeB->id],
);
```

#### Removing nodes

Use `NodeRemovalStrategy` to choose what happens to the removed node's children:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Contracts\CategoryTreeServiceInterface;
use Markommerce\Catalog\Enum\NodeRemovalStrategy;

// Remove node and all its descendants
$categoryTreeService->removeNode($nodeId, NodeRemovalStrategy::CASCADE);

// Remove node; promote its children to the removed node's parent level
$categoryTreeService->removeNode($nodeId, NodeRemovalStrategy::PROMOTE_CHILDREN);
```

#### Reading the tree structure

`getMaterializedTree()` returns the entire tree as a nested array, sorted by `position`:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Contracts\CategoryTreeServiceInterface;

// Returns array<int, array{node: CategoryTreeNode, category_id: int, children: array<int, mixed>}>
$tree = $categoryTreeService->getMaterializedTree($defaultTree->id);

foreach ($tree as $entry) {
    $node = $entry['node'];
    $categoryId = $entry['category_id'];
    $children = $entry['children']; // same structure, recursively
}
```

#### Deleting a category

Categories cannot be deleted while they have active placements in any tree. Use `CategoryService::delete()`, which checks for placements across all trees before removing the record:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Services\CategoryService;
use Markommerce\Catalog\Exceptions\CategoryHasPlacementsException;
use Markommerce\Catalog\Exceptions\CategoryNotFoundException;

try {
    $categoryService->delete($categoryId);
} catch (CategoryHasPlacementsException $e) {
    // Remove the category from all trees first
} catch (CategoryNotFoundException $e) {
    // Category does not exist
}
```

### Looking up a product by SKU

`ProductRepositoryInterface` extends the base `RepositoryInterface` with a `findBySku()` method:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Contracts\ProductRepositoryInterface;

$product = $productRepository->findBySku('SKU-0001'); // ?Product
```

### Retrieving a product by ID with a hard not-found error

`ProductService::getProduct()` wraps `find()` and throws `ProductNotFoundException` instead of returning `null`:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Services\ProductService;
use Markommerce\Catalog\Exceptions\ProductNotFoundException;

try {
    $product = $productService->getProduct($id);
} catch (ProductNotFoundException $e) {
    // Product does not exist
}
```

### Storefront

The catalog storefront route, layout definition, `ProductGridComponent`, `ProductCard`, `StockBadge`, and Latte templates are provided by [markommerce/catalog-storefront](/docs/packages/catalog-storefront/). Install that package to add the `GET /catalog/category/{id}` route and the full product grid UI to your application.

### Pricing

`markommerce/catalog` ships with a full batch pricing pipeline. The pipeline resolves a `Money` value for each product in a set using a chain of `PriceContributorInterface` implementations and a shared `PriceBatch` carrier, keeping the design N+1-free by design.

#### Resolving a single product price

Inject `PriceResolverInterface` and call `resolve()` with a `PriceContext`:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Catalog\Pricing\Exceptions\PriceUnavailableException;
use Markommerce\Catalog\Pricing\PriceContext;

try {
    $money = $priceResolver->resolve(PriceContext::forProduct($product));
} catch (PriceUnavailableException $e) {
    // No priceAmount set on the product
}

echo $money->amount();           // e.g. "29.99"
echo $money->currency()->code;   // e.g. "USD"
```

#### Resolving prices for a batch of products

Inject `BatchPriceResolverInterface` to resolve prices for multiple products in one pass. Only keys with a non-null resolved amount are returned:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Pricing\Contracts\BatchPriceResolverInterface;

// $products is array<array-key, Product>
$prices = $batchPriceResolver->resolve($products);
// Returns array<array-key, Money> — only keyed entries with a resolved amount
```

#### Implementing a PriceContributor

Custom pricing rules (sale prices, tier prices, customer-group discounts) are implemented as `PriceContributorInterface`. Contributors **must** load data set-wise (one query for the full batch, not one per product) and call `setAmount()` for each key:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Pricing\Contracts\PriceContributorInterface;
use Markommerce\Catalog\Pricing\PriceBatch;

class SalePriceContributor implements PriceContributorInterface
{
    public function contribute(PriceBatch $batch): void
    {
        // Load sale prices for all products in one query
        $productIds = array_keys($batch->products());
        $salePrices = $this->salePriceRepository->findForProducts($productIds);

        foreach ($salePrices as $productId => $salePrice) {
            $batch->setAmount($productId, $salePrice);
        }
    }
}
```

Register contributors in your `module.php` boot closure via `PriceContributorRegistry::register()`. The `$priority` parameter controls contributor order --- lower values run first:

```php title="module.php"
<?php

declare(strict_types=1);

use Markommerce\Catalog\Pricing\PriceContributorRegistry;

return [
    'boot' => function (PriceContributorRegistry $priceContributorRegistry, SalePriceContributor $salePriceContributor): void {
        $priceContributorRegistry->register($salePriceContributor, priority: 10);
    },
];
```

The base `BasePriceContributor` (priority `0`) always runs first and seeds the batch with each product's `priceAmount` via `ProductBasePriceProviderInterface`. Subsequent contributors can overwrite any entry.

#### Layering pricing rules via Plugins

For concerns that need to wrap rather than replace the resolved value (e.g. applying a promotional discount after all contributors have run), decorate `PriceResolverInterface` with a Marko `#[Plugin]`:

```php
<?php

declare(strict_types=1);

use Marko\Core\Attributes\Plugin;
use Markommerce\Catalog\Pricing\Contracts\PriceResolverInterface;
use Markommerce\Catalog\Pricing\PriceContext;
use Markommerce\Money\Money;

#[Plugin(plugs: PriceResolverInterface::class, method: 'resolve')]
class PromotionalDiscountPlugin
{
    public function aroundResolve(
        PriceResolverInterface $subject,
        callable $proceed,
        PriceContext $context,
    ): Money {
        $money = $proceed($context);
        // apply discount logic and return the modified Money
        return $money;
    }
}
```

### Seeder

The `catalog` seeder populates 5 sample categories and 5 000 sample products, distributes products across categories, ensures a default category tree exists, and places all categories as root nodes of that tree:

```bash
php artisan db:seed --seeder=catalog
```

To also seed locale-scoped overrides for product and category names, run the `catalog-locale` seeder provided by [markommerce/catalog-scope](/docs/packages/catalog-scope/):

```bash
php artisan db:seed --seeder=catalog-locale
```

## Module Bindings

`module.php` registers the following default bindings. Override any of them in your application's `module.php` to swap the implementation:

| Interface | Default Implementation |
|---|---|
| `ProductRepositoryInterface` | `ProductRepository` |
| `CategoryRepositoryInterface` | `CategoryRepository` |
| `ProductCategoryAssignmentRepositoryInterface` | `ProductCategoryAssignmentRepository` |
| `CategoryTreeRepositoryInterface` | `CategoryTreeRepository` |
| `CategoryTreeNodeRepositoryInterface` | `CategoryTreeNodeRepository` |
| `BatchPriceResolverInterface` | `BatchPriceResolver` |
| `PriceResolverInterface` | `PriceResolver` |
| `ProductBasePriceProviderInterface` | `RawProductBasePriceProvider` |

`PriceContributorRegistry` and `CategorySortOrderRegistry` are registered as singletons. `BasePriceContributor` is registered with priority `0` at boot. The `position` sort order (`ColumnSortOrder`, key `'position'`) is registered in `CategorySortOrderRegistry` at priority `0` at boot.

## API Reference

### Entities

#### `Product`

Table: `catalog_products`

| Property | Type | Column | Notes |
|---|---|---|---|
| `$id` | `?int` | `id` | Primary key, auto-increment |
| `$sku` | `string` | `sku` (unique, length 64) | Globally unique; enforced at DB and service level |
| `$name` | `string` | `name` (length 255) | |
| `$description` | `?string` | `description` (text, nullable) | |
| `$priceAmount` | `?string` | `price_amount` (`decimal(20,4)`, nullable) | Global base price amount as a decimal string. Currency is resolved separately via `CurrencyResolver`. Per-market overrides are registered by [markommerce/catalog-market](/docs/packages/catalog-market/). |

Scoped override support is not built into this entity. Install [markommerce/catalog-scope](/docs/packages/catalog-scope/) to add a `scopes` column and `HasScopesInterface` support via a companion entity.

#### `Category`

Table: `catalog_categories`

| Property | Type | Column | Notes |
|---|---|---|---|
| `$id` | `?int` | `id` | Primary key, auto-increment |
| `$name` | `string` | `name` (length 255) | |
| `$description` | `?string` | `description` (text, nullable) | |

Scoped override support is not built into this entity. Install [markommerce/catalog-scope](/docs/packages/catalog-scope/) to add a `scopes` column and `HasScopesInterface` support via a companion entity.

#### `ProductCategoryAssignment`

Table: `catalog_product_category`

| Property | Type | Column | Notes |
|---|---|---|---|
| `$id` | `?int` | `id` | Primary key, auto-increment |
| `$productId` | `?int` | `product_id` | FK → `catalog_products`, CASCADE on delete |
| `$categoryId` | `?int` | `category_id` | FK → `catalog_categories`, CASCADE on delete |
| `$position` | `int` | `position` (integer, not null) | Sort order within the category; default `0`. Used as the `position` sort key in paginated queries. |

A unique index on `(product_id, category_id)` prevents duplicate assignments at the database level.

#### `CategoryTree`

Table: `catalog_category_trees`

| Property | Type | Column | Notes |
|---|---|---|---|
| `$id` | `?int` | `id` | Primary key, auto-increment |
| `$code` | `string` | `code` (unique, length 64) | Machine-readable identifier; used with `findByCode()` |
| `$name` | `string` | `name` (length 255) | Human-readable label |
| `$isDefault` | `bool` | `is_default` | Exactly one tree must be default; enforced at the service layer |

#### `CategoryTreeNode`

Table: `catalog_category_tree_nodes`

| Property | Type | Column | Notes |
|---|---|---|---|
| `$id` | `?int` | `id` | Primary key, auto-increment |
| `$treeId` | `?int` | `tree_id` | FK → `catalog_category_trees`, CASCADE on delete |
| `$categoryId` | `?int` | `category_id` | FK → `catalog_categories`, RESTRICT on delete |
| `$parentNodeId` | `?int` | `parent_node_id` | FK → `catalog_category_tree_nodes`, CASCADE on delete; `null` for root nodes |
| `$position` | `int` | `position` | Sort order within a sibling group; service uses a gap of 10 |

A unique index on `(tree_id, parent_node_id, position)` prevents two nodes from occupying the same slot. Additional indexes on `(tree_id, category_id)` and `(parent_node_id, position)` support efficient tree queries.

### Interfaces

#### `ProductRepositoryInterface`

Extends `RepositoryInterface<Product>`.

| Method | Return type | Description |
|---|---|---|
| `findBySku(string $sku)` | `?Product` | Find a product by its unique SKU. Returns `null` when no match exists. |

The base `RepositoryInterface` provides `find(int $id): ?Product`, `save(Product $product): void`, `delete(Product $product): void`, and `matching(QuerySpecification ...$specs): array`.

#### `CategoryRepositoryInterface`

Extends `RepositoryInterface<Category>`. No additional methods beyond the base interface.

#### `ProductCategoryAssignmentRepositoryInterface`

Extends `RepositoryInterface<ProductCategoryAssignment>`.

| Method | Return type | Description |
|---|---|---|
| `findByCategory(int $categoryId)` | `array<ProductCategoryAssignment>` | Return all assignments for a given category. |
| `findByProductAndCategory(int $productId, int $categoryId)` | `?ProductCategoryAssignment` | Find a specific product-category pair. Returns `null` when no assignment exists. |

#### `CategoryTreeRepositoryInterface`

Extends `RepositoryInterface<CategoryTree>`.

| Method | Return type | Description |
|---|---|---|
| `findByCode(string $code)` | `?CategoryTree` | Find a tree by its unique code. Returns `null` when no match exists. |
| `findDefault()` | `CategoryTree` | Return the tree marked as default. Throws `DefaultTreeMissingException` when no default is configured. |

#### `CategoryTreeNodeRepositoryInterface`

Extends `RepositoryInterface<CategoryTreeNode>`.

| Method | Return type | Description |
|---|---|---|
| `findByTree(int $treeId)` | `list<CategoryTreeNode>` | Return all nodes belonging to a tree. |
| `findChildren(?int $parentNodeId, int $treeId)` | `list<CategoryTreeNode>` | Return direct children of the given parent node, sorted by position. Pass `null` for `$parentNodeId` to retrieve root nodes. |
| `findRoots(int $treeId)` | `list<CategoryTreeNode>` | Convenience shortcut for `findChildren(null, $treeId)`. |
| `findByCategoryInTree(int $categoryId, int $treeId)` | `list<CategoryTreeNode>` | Return all placements of a category within a specific tree (multi-placement support). |
| `findByCategoryAcrossTrees(int $categoryId)` | `list<CategoryTreeNode>` | Return all placements of a category across all trees. Used by the category deletion guard. |

#### `CategoryTreeServiceInterface`

The primary contract for category tree lifecycle management. Implemented by `CategoryTreeService`. Third-party packages can target this interface with Marko Plugins --- [markommerce/catalog-market](/docs/packages/catalog-market/) uses it as the plugin target for its tree-deletion guard.

| Method | Return type | Throws | Description |
|---|---|---|---|
| `createTree(string $code, string $name, bool $isDefault = false)` | `CategoryTree` | `DuplicateDefaultTreeException`, `\InvalidArgumentException` | Create a new tree. |
| `setDefaultTree(int $treeId)` | `void` | `CategoryTreeNotFoundException` | Promote a tree to default; demotes the current default. |
| `deleteTree(int $treeId)` | `void` | `CategoryTreeNotFoundException`, `CannotDeleteDefaultTreeException` | Delete a tree. Blocked when the tree is the default. |
| `ensureDefaultTreeExists()` | `CategoryTree` | --- | Return the existing default or create one. Idempotent. |
| `placeCategory(int $treeId, int $categoryId, ?int $parentNodeId = null, ?int $position = null)` | `CategoryTreeNode` | `CategoryTreeNotFoundException`, `CategoryNotFoundException`, `CategoryTreeNodeNotFoundException`, `NodeNotInTreeException` | Place a category in a tree as a node. |
| `moveNode(int $nodeId, ?int $newParentNodeId, int $position)` | `void` | `CategoryTreeNodeNotFoundException`, `NodeNotInTreeException`, `CircularNodeReferenceException` | Move a node to a new parent and position. |
| `removeNode(int $nodeId, NodeRemovalStrategy $strategy)` | `void` | `CategoryTreeNodeNotFoundException` | Remove a node using `CASCADE` or `PROMOTE_CHILDREN`. |
| `reorderSiblings(?int $parentNodeId, int $treeId, array $orderedNodeIds)` | `void` | `CategoryTreeNodeNotFoundException`, `NodeNotInTreeException` | Reorder siblings by supplying an ordered list of node IDs. |
| `getMaterializedTree(int $treeId)` | `array` | `CategoryTreeNotFoundException` | Return the full tree as a nested array of `{node, category_id, children}` entries, sorted by position. |

### Services

#### `ProductService`

| Method | Return type | Throws | Description |
|---|---|---|---|
| `createProduct(string $sku, string $name, ?string $description = null)` | `Product` | `DuplicateSkuException` | Create and persist a new product. Throws when an existing product with the same SKU is found. |
| `getProduct(int $id)` | `Product` | `ProductNotFoundException` | Load a product by ID. Throws instead of returning `null`. |

#### `CategoryAssignmentService`

| Method | Return type | Throws | Description |
|---|---|---|---|
| `assign(int $productId, int $categoryId)` | `void` | `ProductNotFoundException`, `CategoryNotFoundException` | Assign a product to a category. No-op if already assigned. |
| `detach(int $productId, int $categoryId)` | `void` | --- | Remove a product-category assignment. No-op if no assignment exists. |
| `productsInCategory(int $categoryId)` | `list<Product>` | `CategoryNotFoundException` | Return all products assigned to the given category (N+1 per-product lookup). |
| `paginatedProductsInCategory(int $categoryId, ResolvedPaginationOptions $resolvedPaginationOptions)` | `Page<Product>` | `CategoryNotFoundException`, `RepositoryException` | Return a paginated page of products via a single JOIN query. The returned `Page` implements `RandomAccessPageInterface` when the offset strategy is active. |

#### `CategoryTreeService`

| Method | Return type | Throws | Description |
|---|---|---|---|
| `createTree(string $code, string $name, bool $isDefault = false)` | `CategoryTree` | `DuplicateDefaultTreeException`, `\InvalidArgumentException` | Create and persist a new tree. Throws when `$code` is empty or when `$isDefault` is `true` and a default already exists. |
| `setDefaultTree(int $treeId)` | `void` | `CategoryTreeNotFoundException` | Promote a tree to default. Demotes the current default automatically. |
| `deleteTree(int $treeId)` | `void` | `CategoryTreeNotFoundException`, `CannotDeleteDefaultTreeException` | Delete a tree. Blocked when the tree is the default. Market assignment guard is provided by [markommerce/catalog-market](/docs/packages/catalog-market/). |
| `ensureDefaultTreeExists()` | `CategoryTree` | --- | Return the existing default tree or create one with `code='default'` and `name='Default'`. Idempotent. |
| `placeCategory(int $treeId, int $categoryId, ?int $parentNodeId = null, ?int $position = null)` | `CategoryTreeNode` | `CategoryTreeNotFoundException`, `CategoryNotFoundException`, `CategoryTreeNodeNotFoundException`, `NodeNotInTreeException` | Create a node placing a category in a tree. Omit `$position` to append after existing siblings. |
| `moveNode(int $nodeId, ?int $newParentNodeId, int $position)` | `void` | `CategoryTreeNodeNotFoundException`, `NodeNotInTreeException`, `CircularNodeReferenceException` | Move a node to a new parent and position. Cycle detection prevents a node from becoming its own ancestor. |
| `removeNode(int $nodeId, NodeRemovalStrategy $strategy)` | `void` | `CategoryTreeNodeNotFoundException` | Remove a node using `CASCADE` (delete subtree) or `PROMOTE_CHILDREN` (reparent children to removed node's parent). |
| `reorderSiblings(?int $parentNodeId, int $treeId, array $orderedNodeIds)` | `void` | `CategoryTreeNodeNotFoundException`, `NodeNotInTreeException` | Reorder a sibling group by supplying an ordered list of node IDs. Positions are reassigned as multiples of 10. |
| `getMaterializedTree(int $treeId)` | `array` | `CategoryTreeNotFoundException` | Return the full tree as a nested array of `{node, category_id, children}` entries, sorted by position. Result is cached in-memory until the tree is modified. |

#### `CategoryService`

| Method | Return type | Throws | Description |
|---|---|---|---|
| `delete(int $categoryId)` | `void` | `CategoryNotFoundException`, `CategoryHasPlacementsException` | Delete a category. Throws when the category has active placements in any tree. |

### Pagination

#### `PaginationOptionsResolver`

Translates raw HTTP request parameters into a `ResolvedPaginationOptions` value object. All config values are read from `CatalogPaginationConfig` via the config system.

| Method | Return type | Throws | Description |
|---|---|---|---|
| `resolve(?int $page, ?int $size, ?string $sort)` | `ResolvedPaginationOptions` | `InvalidPaginationConfigException`, `PageDepthExceededException` | Resolve and validate pagination options. Pass `null` for any parameter to use the configured default. |

#### `ResolvedPaginationOptions`

Immutable value object produced by `PaginationOptionsResolver`.

| Property | Type | Description |
|---|---|---|
| `$sortOrder` | `CategorySortOrderInterface` | Resolved sort order instance (used by `CategoryAssignmentService` to apply JOINs and ORDER BY) |
| `$size` | `int` | Resolved page size |
| `$page` | `int` | Resolved page number (1-based) |
| `$presentation` | `PaginationPresentation` | Active storefront presentation mode |
| `$strategyKind` | `PaginationStrategyKind` | Active pagination strategy |
| `$countMode` | `CountMode` | Active count mode |

#### `PaginationPresentation` (enum)

| Case | Value | Description |
|---|---|---|
| `Numbered` | `'numbered'` | Numbered page links; requires `strategy=offset` |
| `LoadMore` | `'load_more'` | Load-more button appending to the existing list |
| `Infinite` | `'infinite'` | Infinite scroll |

#### `PaginationStrategyKind` (enum)

| Case | Value | Description |
|---|---|---|
| `Offset` | `'offset'` | Classic LIMIT/OFFSET pagination; supports random access and numbered pages |
| `Keyset` | `'keyset'` | Seek-based (cursor) pagination; sequential only |

#### `CountMode` (enum)

| Case | Value | Description |
|---|---|---|
| `Exact` | `'exact'` | `SELECT COUNT(*)` for accurate totals |
| `Estimated` | `'estimated'` | Planner estimate with exact fallback |

### Sorting

#### `CategorySortOrderInterface`

The contract for all category product listing sort orders. Implement this interface to contribute a new sort option that any package (including third-party modules) can register.

| Method | Return type | Description |
|---|---|---|
| `key()` | `string` | Stable URL/config token that uniquely identifies this sort order (e.g. `price_asc`). Used as the `sort` query parameter value. |
| `label()` | `string` | Human-readable label shown in the storefront sort-order dropdown. |
| `supportsKeyset()` | `bool` | Whether this sort order can be used with `strategy=keyset`. Sort orders that rely on a LEFT JOIN (e.g. price) must return `false`; column-only sorts may return `true` if they produce a deterministic order. |
| `prepareQuery(RepositoryQueryBuilder $repositoryQueryBuilder)` | `void` | Add any JOINs required by this sort order to the category product query. Plain column sorts implement this as a no-op. |
| `sortFields()` | `list<SortField>` | The sort fields to apply to the ORDER BY clause. |

#### `CategorySortOrderRegistry`

Singleton that holds all registered sort orders. Used by `PaginationOptionsResolver` at resolution time and by `ProductGridComponent` to build the storefront dropdown.

| Method | Return type | Description |
|---|---|---|
| `register(CategorySortOrderInterface $categorySortOrder, int $priority = 0)` | `void` | Register a sort order. Lower priority values appear first. No-op if an order with the same key is already registered. |
| `all()` | `list<CategorySortOrderInterface>` | Return all registered sort orders sorted by priority (ascending). |
| `get(string $key)` | `?CategorySortOrderInterface` | Find a sort order by its key. Returns `null` when no match exists. |
| `has(string $key)` | `bool` | Whether a sort order with the given key is registered. |

#### `ColumnSortOrder`

A built-in `CategorySortOrderInterface` implementation for sort orders that map directly to a single database column and require no additional JOINs. Construct it inline in your `module.php` boot closure instead of writing a dedicated class.

| Constructor parameter | Type | Description |
|---|---|---|
| `$key` | `string` | Stable URL/config token |
| `$label` | `string` | Human-readable label |
| `$column` | `string` | Fully-qualified column name (e.g. `catalog_products.name`) |
| `$direction` | `SortDirection` | `Ascending` (default) or `Descending` |
| `$supportsKeyset` | `bool` | Whether this order is compatible with keyset pagination (default `false`) |
| `$nulls` | `?NullsPlacement` | `NullsPlacement::First`, `NullsPlacement::Last`, or `null` (default; database default applies) |

### Enums

#### `NodeRemovalStrategy`

| Case | Description |
|---|---|
| `CASCADE` | Delete the node and all of its descendants. |
| `PROMOTE_CHILDREN` | Delete the node and move its direct children up to the removed node's parent. |

### Exceptions

All exceptions extend `MarkoException` and carry a `message`, `context`, and `suggestion`.

| Exception | Named constructor | When thrown |
|---|---|---|
| `DuplicateSkuException` | `forSku(string $sku)` | `ProductService::createProduct()` finds an existing product with the same SKU |
| `ProductNotFoundException` | `forId(int $id)` | `ProductService::getProduct()` or `CategoryAssignmentService::assign()` cannot find the product |
| `CategoryNotFoundException` | `forId(int $id)` | `CategoryAssignmentService::assign()`, `productsInCategory()`, or `CategoryService::delete()` cannot find the category |
| `CategoryHasPlacementsException` | `forCategory(int $categoryId, int $placementCount)` | `CategoryService::delete()` finds active tree placements for the category |
| `CategoryTreeNotFoundException` | `forId(int $id)`, `forCode(string $code)` | A `CategoryTreeService` method cannot find the requested tree |
| `DefaultTreeMissingException` | `forResolution()` | `CategoryTreeRepositoryInterface::findDefault()` finds no default tree configured |
| `DuplicateDefaultTreeException` | `forCode(string $code)` | `CategoryTreeService::createTree()` is called with `$isDefault = true` when a default already exists |
| `CannotDeleteDefaultTreeException` | `forTreeId(int $treeId)` | `CategoryTreeService::deleteTree()` is called on the active default tree |
| `CategoryTreeNodeNotFoundException` | `forId(int $id)` | A `CategoryTreeService` method cannot find the requested node |
| `NodeNotInTreeException` | `forNodeAndTree(int $nodeId, int $expectedTreeId, int $actualTreeId)`, `forParentMismatch(int $nodeId, ?int $expectedParentNodeId, ?int $actualParentNodeId)` | A node is referenced against the wrong tree, or a sibling group contains a node with a mismatched parent |
| `CircularNodeReferenceException` | `forNodeAndParent(int $nodeId, int $proposedParentId)` | `CategoryTreeService::moveNode()` detects that the proposed parent is a descendant of the node being moved |
| `InvalidPaginationConfigException` | `forUnknownStrategy()`, `forUnknownPresentation()`, `forUnsupportedCountMode()`, `forInvalidSort()`, `forNumberedKeysetCombination()`, `forKeysetIncompatibleSort(string $sortKey)` | `PaginationOptionsResolver::resolve()` receives an invalid config value, an incompatible strategy+presentation combination, or a sort order that does not support keyset pagination when `strategy=keyset` is active |
| `PageDepthExceededException` | `forDepth(int $page, int $max)` | `PaginationOptionsResolver::resolve()` is called with a page number exceeding `maxPageDepth` |

### Pricing

#### `PriceContext`

Readonly value object. Constructed via the static factory only.

| Property | Type | Description |
|---|---|---|
| `$product` | `Product` | The product being priced. Must carry `ProductScopedOverrides` for market-scoped resolution. |
| `$market` | `?string` | Optional market identifier passed through to plugins; not used directly by the batch pipeline. |

| Method | Return type | Description |
|---|---|---|
| `PriceContext::forProduct(Product $product, ?string $market = null)` | `PriceContext` | Construct a price context for a product, optionally scoped to a market. |

#### `PriceResolverInterface`

| Method | Return type | Throws | Description |
|---|---|---|---|
| `resolve(PriceContext $context)` | `Money` | `PriceUnavailableException` | Resolve the effective price for the given context as a `Money` value. Delegates to `BatchPriceResolverInterface` internally. |

Default implementation: `PriceResolver`.

#### `BatchPriceResolverInterface`

| Method | Return type | Description |
|---|---|---|
| `resolve(array $products)` | `array<array-key, Money>` | Run the full contributor pipeline over a `array<array-key, Product>` map. Returns only keys with a non-null resolved amount. |

Default implementation: `BatchPriceResolver`. Runs all contributors registered in `PriceContributorRegistry` in priority order, then converts surviving amounts to `Money` objects using the base currency from `CurrencyResolver`.

#### `PriceContributorInterface`

| Method | Return type | Description |
|---|---|---|
| `contribute(PriceBatch $batch)` | `void` | Seed or overwrite amounts in the batch. Must be set-wise (no per-product queries). |

#### `PriceBatch`

Carrier passed to every contributor in sequence.

| Method | Return type | Throws | Description |
|---|---|---|---|
| `PriceBatch::of(array $products, Currency $currency)` | `PriceBatch` | --- | Static factory. |
| `products()` | `array<array-key, Product>` | --- | The full product map for this batch. |
| `currency()` | `Currency` | --- | The active currency for this batch. |
| `amount(int\|string $key)` | `?string` | --- | Current decimal amount for a key, or `null` if not yet set. |
| `setAmount(int\|string $key, ?string $amount)` | `void` | `InvalidBatchKeyException` | Set the decimal amount for a product key. Throws when the key is not in the batch. |
| `keys()` | `list<array-key>` | --- | All product keys in this batch. |

#### `PriceContributorRegistry`

Singleton that holds all registered contributors. Used by `BatchPriceResolver` at resolution time.

| Method | Return type | Description |
|---|---|---|
| `register(PriceContributorInterface $priceContributor, int $priority = 0)` | `void` | Register a contributor. Lower priority values run first. |
| `all()` | `list<PriceContributorInterface>` | Return all contributors sorted by priority (ascending). |

#### `ProductBasePriceProviderInterface`

| Method | Return type | Description |
|---|---|---|
| `amountsFor(array $products)` | `array<array-key, ?string>` | Return raw decimal amounts keyed by the same keys as the input `array<array-key, Product>` map. |

Default implementation: `RawProductBasePriceProvider` --- reads `Product::$priceAmount` directly. Overridden by [markommerce/catalog-market](/docs/packages/catalog-market/) with `ScopedProductBasePriceProvider`, which resolves the market-scoped amount via `ScopeResolver`.

#### Pricing Exceptions

| Exception | Named constructor | When thrown |
|---|---|---|
| `PriceUnavailableException` | `forContext(PriceContext $context)` | `PriceResolver::resolve()` finds no resolved amount for the product. Includes SKU and market in the context message. |
| `InvalidBatchKeyException` | `forKey(int\|string $key)` | `PriceBatch::setAmount()` is called with a key not present in the batch's product map. |

## Related Packages

- [markommerce/criteria](/docs/packages/criteria/) --- Pagination engine used by `CategoryAssignmentService::paginatedProductsInCategory()`; provides `PaginationStrategyInterface`, `PageRequest`, and `Page`
- [markommerce/catalog-storefront](/docs/packages/catalog-storefront/) --- Storefront route, layout definition, product grid and card components for `markommerce/catalog`
- [markommerce/catalog-scope](/docs/packages/catalog-scope/) --- Adds `HasScopesInterface` support to `Product` and `Category` via companion entities; required if you want scoped overrides on catalog entities
- [markommerce/catalog-locale](/docs/packages/catalog-locale/) --- Bridge that registers `name` and `description` as locale-scoped on `Product` and `Category`
- [markommerce/catalog-market](/docs/packages/catalog-market/) --- Per-market category tree assignment, resolution, deletion guard plugin, and per-market `priceAmount` override registration
- [markommerce/catalog-price-index](/docs/packages/catalog-price-index/) --- Denormalized price index table for fast sorting and filtering; populated by the `BatchPriceResolverInterface` pipeline built into this package
- [markommerce/scope](/docs/packages/scope/) --- Scoped attribute resolution engine
- [markommerce/scope-pgsql](/docs/packages/scope-pgsql/) --- PostgreSQL driver required to persist and query scoped overrides
