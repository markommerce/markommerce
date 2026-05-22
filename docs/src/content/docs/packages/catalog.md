---
title: markommerce/catalog
description: Products and categories for Markommerce — locale-scoped names, globally-unique SKUs, and a ready-made storefront route.
---

Products and categories for Markommerce. `markommerce/catalog` provides the `Product` and `Category` entities, three repository interfaces, two services, a storefront controller, and a database seeder. Products carry a globally-unique SKU and locale-scoped `name`/`description` fields; categories carry locale-scoped `name`/`description` fields. All scoped fields use `markommerce/scope` for per-locale value resolution with automatic hierarchy fallback.

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

To add locale-scoped overrides, call `setOverride()` on the entity directly and save via the repository:

```php
<?php

declare(strict_types=1);

use Markommerce\Catalog\Contracts\ProductRepositoryInterface;

$product->setOverride('locale:de', 'name', 'Widget DE');
$product->setOverride('locale:fr', 'name', 'Widget FR');
$productRepository->save($product);
```

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
$category->setOverride('locale:de', 'name', 'Widgets DE');
$category->setOverride('locale:fr', 'name', 'Widgets FR');
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

`ProductGridComponent` is a placement-agnostic component. Its `data(Category $category)` method receives the resolved `Category` context object, loads the assigned products, and resolves locale-scoped `name` and `description` via `ScopeResolver`. It returns a `ProductGridData` DTO:

| Property | Type | Description |
|---|---|---|
| `$category` | `Category` | The resolved category entity |
| `$products` | `list<Product>` | All products assigned to the category |
| `$resolvedNames` | `array<int, string>` | Locale-resolved name keyed by product ID |
| `$resolvedDescs` | `array<int, string\|null>` | Locale-resolved description keyed by product ID |
| `$extensions` | `ExtensionBag` | Typed extension attributes (third-party use) |

### ProductCard and ProductCardData

`ProductCard` is the per-item component rendered inside the `products` repeat slot. Its `data(Product $product)` method returns a `ProductCardData` DTO:

| Property | Type | Description |
|---|---|---|
| `$product` | `Product` | The product entity |
| `$resolvedName` | `string` | Locale-resolved product name |
| `$resolvedDesc` | `string` | Locale-resolved product description |
| `$inStock` | `bool` | Whether the product is currently in stock |
| `$extensions` | `ExtensionBag` | Typed extension attributes (third-party use) |

Both `ProductGridData` and `ProductCardData` extend `ExtensibleData`, allowing third-party modules to attach typed extension attributes via `withExtension()` without subclassing the DTO. See [markommerce/layout](/docs/packages/layout/) for details on the extension attribute pattern.

### Seeder

The `catalog` seeder populates 5 sample categories and 30 sample products, each with `locale:de` and `locale:fr` overrides, and distributes products across categories:

```bash
php artisan db:seed --seeder=catalog
```

> **Note:** The `locale:de` and `locale:fr` overrides only resolve once those locales are registered in `config/scope.php`. See [markommerce/scope](/docs/packages/scope/) for axis configuration.

## Module Bindings

`module.php` registers the following default bindings. Override any of them in your application's `module.php` to swap the implementation:

| Interface | Default Implementation |
|---|---|
| `ProductRepositoryInterface` | `ProductRepository` |
| `CategoryRepositoryInterface` | `CategoryRepository` |
| `ProductCategoryAssignmentRepositoryInterface` | `ProductCategoryAssignmentRepository` |

## API Reference

### Entities

#### `Product`

Table: `catalog_products`

| Property | Type | Column | Notes |
|---|---|---|---|
| `$id` | `?int` | `id` | Primary key, auto-increment |
| `$sku` | `string` | `sku` (unique, length 64) | Globally unique; enforced at DB and service level |
| `$name` | `string` | `name` (length 255) | Scoped to `locale` axis |
| `$description` | `?string` | `description` (text, nullable) | Scoped to `locale` axis |

Implements `HasScopesInterface` via the `HasScopes` trait. Use `setOverride(string $signature, string $property, mixed $value)` to attach locale-scoped values before persisting.

#### `Category`

Table: `catalog_categories`

| Property | Type | Column | Notes |
|---|---|---|---|
| `$id` | `?int` | `id` | Primary key, auto-increment |
| `$name` | `string` | `name` (length 255) | Scoped to `locale` axis |
| `$description` | `?string` | `description` (text, nullable) | Scoped to `locale` axis |

Implements `HasScopesInterface` via the `HasScopes` trait.

#### `ProductCategoryAssignment`

Table: `catalog_product_category`

| Property | Type | Column | Notes |
|---|---|---|---|
| `$id` | `?int` | `id` | Primary key, auto-increment |
| `$productId` | `?int` | `product_id` | FK → `catalog_products`, CASCADE on delete |
| `$categoryId` | `?int` | `category_id` | FK → `catalog_categories`, CASCADE on delete |

A unique index on `(product_id, category_id)` prevents duplicate assignments at the database level.

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

### Exceptions

All exceptions extend `MarkoException` and carry a `message`, `context`, and `suggestion`.

| Exception | Named constructor | When thrown |
|---|---|---|
| `DuplicateSkuException` | `forSku(string $sku)` | `ProductService::createProduct()` finds an existing product with the same SKU |
| `ProductNotFoundException` | `forId(int $id)` | `ProductService::getProduct()` or `CategoryAssignmentService::assign()` cannot find the product |
| `CategoryNotFoundException` | `forId(int $id)` | `CategoryAssignmentService::assign()` or `productsInCategory()` cannot find the category |

## Related Packages

- [markommerce/scope](/docs/packages/scope/) --- Scoped attribute resolution used by `Product` and `Category` entities
- [markommerce/scope-pgsql](/docs/packages/scope-pgsql/) --- PostgreSQL driver required to persist and query scoped overrides
- [markommerce/layout](/docs/packages/layout/) --- Layout resolution, typed component data DTOs, and extension operations used by the category storefront page
- [markommerce/theme-blank](/docs/packages/theme-blank/) --- Provides `OneColumnLayout` and other `LayoutDefinition` classes extended by the category layout
