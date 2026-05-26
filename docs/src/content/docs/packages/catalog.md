---
title: markommerce/catalog
description: Products and categories for Markommerce — globally-unique SKUs, per-market category trees, and a ready-made storefront route.
---

Products and categories for Markommerce. `markommerce/catalog` provides the `Product` and `Category` entities, repository interfaces, services, a storefront controller, and a database seeder. Products carry a globally-unique SKU; categories carry a name and optional description. Category placement in navigation is managed separately through category trees --- each market can have its own tree or fall back to a shared default, and the same category may appear at multiple positions within a tree. Scope support is not built into `markommerce/catalog` --- the package ships with no scope dependency. To add locale-scoped overrides to catalog entities, install [markommerce/catalog-scope](/docs/packages/catalog-scope/) (storage layer) and [markommerce/catalog-locale](/docs/packages/catalog-locale/) (field registration bridge).

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

// List products in a category
$products = $categoryAssignmentService->productsInCategory($category->id);
```

Both `assign()` and `productsInCategory()` throw `ProductNotFoundException` or `CategoryNotFoundException` when the referenced entity does not exist.

### Working with category trees

Category trees separate a category's identity from its position in navigation. A tree is identified by a unique `code` and can be marked as the default tree. Markets can be assigned to a specific tree; any market without an explicit assignment uses the default tree.

#### Creating and configuring trees

Use `CategoryTreeService` to create trees and manage the default:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Services\CategoryTreeService;
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

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Services\CategoryTreeService;

// Assign a specific tree to a market
$categoryTreeService->assignTreeToMarket($euTree->id, 'market:eu');

// Remove a market's explicit assignment (falls back to default)
$categoryTreeService->unassignMarket('market:eu');

// Resolve the active tree for a market
$tree = $categoryTreeService->resolveTreeForMarket('market:eu');
```

#### Placing categories in a tree

A category can be placed at multiple positions within the same tree. Pass `null` for `$parentNodeId` to create a root-level placement; omit `$position` to append after existing siblings with an automatic gap of 10:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Services\CategoryTreeService;

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

use Markommerce\Catalog\Services\CategoryTreeService;
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

use Markommerce\Catalog\Services\CategoryTreeService;
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

use Markommerce\Catalog\Services\CategoryTreeService;

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

### Storefront route

The catalog module registers a storefront route automatically:

```
GET /catalog/category/{id}
```

`CategoryController` performs a quick category lookup and returns a `404` response when the category ID does not exist. The page is rendered by `markommerce/layout` --- `CategoryController` carries no `#[Layout]` attribute; placement is described entirely in `packages/catalog/layout/category_show.php`.

### Layout definition

The category page layout is declared in `layout/category_show.php`. It extends `OneColumnLayout` from `markommerce/theme-blank`, provides the category via `CategoryDataProvider`, places `ProductGridComponent` in the `content` slot, and uses a `Slot::repeat()` to render a `ProductCard` for each product:

```php title="packages/catalog/layout/category_show.php"
<?php

declare(strict_types=1);

use Markommerce\Catalog\Component\ProductCard;
use Markommerce\Catalog\Component\ProductGridComponent;
use Markommerce\Catalog\Context\CategoryDataProvider;
use Markommerce\Catalog\Context\CategoryToken;
use Markommerce\Catalog\Controller\CategoryController;
use Markommerce\Catalog\Entity\Product;
use Markommerce\Catalog\Iteration\ProductIteration;
use Markommerce\Layout\Layout;
use Markommerce\Layout\Place;
use Markommerce\Layout\Provide;
use Markommerce\Layout\Slot;
use Markommerce\Layout\Source\Source;
use Markommerce\ThemeBlank\Layout\OneColumnLayout;

return new Layout(
    handle: [CategoryController::class, 'show'],
    extends: OneColumnLayout::class,
    context: [
        new Provide(
            token: CategoryToken::class,
            provider: CategoryDataProvider::class,
            props: ['id' => Source::route('id', 'int')],
        ),
    ],
    slots: [
        'content' => [
            new Place(
                component: ProductGridComponent::class,
                name: 'catalog.product_grid',
                props: ['category' => Source::context(CategoryToken::class)],
                slots: [
                    'products' => Slot::repeat(
                        dataKey: 'products',
                        yields: Product::class,
                        as: ProductIteration::class,
                        children: [
                            new Place(
                                component: ProductCard::class,
                                name: 'catalog.product_card',
                                props: ['product' => Source::iterated(ProductIteration::class)],
                                slots: [],
                            ),
                        ],
                    ),
                ],
            ),
        ],
    ],
);
```

### ProductGridComponent

`ProductGridComponent` is a placement-agnostic component. Its `data(Category $category)` method receives the resolved `Category` context object, loads the assigned products, and populates `resolvedNames` and `resolvedDescs` with the raw entity values. It returns a `ProductGridData` DTO. When [markommerce/catalog-scope](/docs/packages/catalog-scope/) is installed, its `ScopedProductGridComponent` Preference replaces this component and performs locale-aware resolution instead:

| Property | Type | Description |
|---|---|---|
| `$category` | `Category` | The resolved category entity |
| `$products` | `list<Product>` | All products assigned to the category |
| `$resolvedNames` | `array<int, string>` | Display name keyed by product ID (raw value; scope-resolved when `catalog-scope` is installed) |
| `$resolvedDescs` | `array<int, string\|null>` | Display description keyed by product ID (raw value; scope-resolved when `catalog-scope` is installed) |
| `$extensions` | `ExtensionBag` | Typed extension attributes (third-party use) |

### ProductCard and ProductCardData

`ProductCard` is the per-item component rendered inside the `products` repeat slot. Its `data(Product $product)` method returns a `ProductCardData` DTO:

| Property | Type | Description |
|---|---|---|
| `$product` | `Product` | The product entity |
| `$resolvedName` | `string` | Display name (raw value; scope-resolved when `catalog-scope` is installed) |
| `$resolvedDesc` | `string` | Display description (raw value; scope-resolved when `catalog-scope` is installed) |
| `$inStock` | `bool` | Whether the product is currently in stock |
| `$extensions` | `ExtensionBag` | Typed extension attributes (third-party use) |

Both `ProductGridData` and `ProductCardData` extend `ExtensibleData`, allowing third-party modules to attach typed extension attributes via `withExtension()` without subclassing the DTO. See [markommerce/layout](/docs/packages/layout/) for details on the extension attribute pattern.

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
| `CategoryTreeMarketAssignmentRepositoryInterface` | `CategoryTreeMarketAssignmentRepository` |

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

#### `CategoryTreeMarketAssignment`

Table: `catalog_category_tree_market_assignments`

| Property | Type | Column | Notes |
|---|---|---|---|
| `$market` | `string` | `market` (PK, length 64) | Market identifier; composite primary key |
| `$treeId` | `?int` | `tree_id` | FK → `catalog_category_trees`, RESTRICT on delete |

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

#### `CategoryTreeMarketAssignmentRepositoryInterface`

Extends `RepositoryInterface<CategoryTreeMarketAssignment>`.

| Method | Return type | Description |
|---|---|---|
| `findByMarket(string $market)` | `?CategoryTreeMarketAssignment` | Return the assignment for a given market. Returns `null` when no explicit assignment exists. |
| `findByTree(int $treeId)` | `list<CategoryTreeMarketAssignment>` | Return all market assignments for a given tree. |

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
| `productsInCategory(int $categoryId)` | `list<Product>` | `CategoryNotFoundException` | Return all products assigned to the given category. |

#### `CategoryTreeService`

| Method | Return type | Throws | Description |
|---|---|---|---|
| `createTree(string $code, string $name, bool $isDefault = false)` | `CategoryTree` | `DuplicateDefaultTreeException`, `\InvalidArgumentException` | Create and persist a new tree. Throws when `$code` is empty or when `$isDefault` is `true` and a default already exists. |
| `setDefaultTree(int $treeId)` | `void` | `CategoryTreeNotFoundException` | Promote a tree to default. Demotes the current default automatically. |
| `deleteTree(int $treeId)` | `void` | `CategoryTreeNotFoundException`, `CannotDeleteDefaultTreeException`, `TreeHasMarketAssignmentsException` | Delete a tree. Blocked when the tree is the default or has active market assignments. |
| `assignTreeToMarket(int $treeId, string $market)` | `void` | `CategoryTreeNotFoundException` | Assign a specific tree to a market. |
| `unassignMarket(string $market)` | `void` | --- | Remove a market's explicit tree assignment. No-op if the market has no assignment. |
| `resolveTreeForMarket(string $market)` | `CategoryTree` | `CategoryTreeNotFoundException`, `DefaultTreeMissingException` | Return the tree assigned to a market, or the default tree when no explicit assignment exists. |
| `ensureDefaultTreeExists()` | `CategoryTree` | --- | Return the existing default tree or create one with `code='default'` and `name='Default'`. Idempotent. |
| `placeCategory(int $treeId, int $categoryId, ?int $parentNodeId = null, ?int $position = null)` | `CategoryTreeNode` | `CategoryTreeNotFoundException`, `CategoryNotFoundException`, `CategoryTreeNodeNotFoundException`, `NodeNotInTreeException` | Create a node placing a category in a tree. Omit `$position` to append after existing siblings. |
| `moveNode(int $nodeId, ?int $newParentNodeId, int $position)` | `void` | `CategoryTreeNodeNotFoundException`, `NodeNotInTreeException`, `CircularNodeReferenceException` | Move a node to a new parent and position. Cycle detection prevents a node from becoming its own ancestor. |
| `removeNode(int $nodeId, NodeRemovalStrategy $strategy)` | `void` | `CategoryTreeNodeNotFoundException` | Remove a node using `CASCADE` (delete subtree) or `PROMOTE_CHILDREN` (reparent children to removed node's parent). |
| `reorderSiblings(?int $parentNodeId, int $treeId, array $orderedNodeIds)` | `void` | `CategoryTreeNodeNotFoundException`, `NodeNotInTreeException` | Reorder a sibling group by supplying an ordered list of node IDs. Positions are reassigned as multiples of 10. |
| `getMaterializedTree(int $treeId)` | `array` | `CategoryTreeNotFoundException` | Return the full tree as a nested array keyed by node ID, sorted by position. Result is cached in-memory until the tree is modified. |

#### `CategoryService`

| Method | Return type | Throws | Description |
|---|---|---|---|
| `delete(int $categoryId)` | `void` | `CategoryNotFoundException`, `CategoryHasPlacementsException` | Delete a category. Throws when the category has active placements in any tree. |

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
| `DefaultTreeMissingException` | `forResolution()` | `CategoryTreeRepositoryInterface::findDefault()` or `resolveTreeForMarket()` finds no default tree configured |
| `DuplicateDefaultTreeException` | `forCode(string $code)` | `CategoryTreeService::createTree()` is called with `$isDefault = true` when a default already exists |
| `CannotDeleteDefaultTreeException` | `forTreeId(int $treeId)` | `CategoryTreeService::deleteTree()` is called on the active default tree |
| `TreeHasMarketAssignmentsException` | `forTreeId(int $treeId, array $markets)` | `CategoryTreeService::deleteTree()` is called on a tree that still has market assignments |
| `CategoryTreeNodeNotFoundException` | `forId(int $id)` | A `CategoryTreeService` method cannot find the requested node |
| `NodeNotInTreeException` | `forNodeAndTree(int $nodeId, int $expectedTreeId, int $actualTreeId)`, `forParentMismatch(int $nodeId, ?int $expectedParentNodeId, ?int $actualParentNodeId)` | A node is referenced against the wrong tree, or a sibling group contains a node with a mismatched parent |
| `CircularNodeReferenceException` | `forNodeAndParent(int $nodeId, int $proposedParentId)` | `CategoryTreeService::moveNode()` detects that the proposed parent is a descendant of the node being moved |

## Related Packages

- [markommerce/catalog-scope](/docs/packages/catalog-scope/) --- Adds `HasScopesInterface` support to `Product` and `Category` via companion entities; required if you want scoped overrides on catalog entities
- [markommerce/catalog-locale](/docs/packages/catalog-locale/) --- Bridge that registers `name` and `description` as locale-scoped on `Product` and `Category`
- [markommerce/scope](/docs/packages/scope/) --- Scoped attribute resolution engine
- [markommerce/scope-pgsql](/docs/packages/scope-pgsql/) --- PostgreSQL driver required to persist and query scoped overrides
- [markommerce/layout](/docs/packages/layout/) --- Layout resolution, typed component data DTOs, and extension operations used by the category storefront page
- [markommerce/theme-blank](/docs/packages/theme-blank/) --- Provides `OneColumnLayout` and other `LayoutDefinition` classes extended by the category layout
