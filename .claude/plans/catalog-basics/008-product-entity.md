# Task 008: Create the Product entity

**Status**: completed
**Depends on**: 005, 007
**Retry count**: 0

## Description
Define `Markommerce\Catalog\Entity\Product` — the core product aggregate. Pure data: id, sku, name, basePriceAmount, and a `BelongsToMany` link to `Category` through `ProductCategory`. The entity is **currency-unaware** — no Money accessor, no currency config dependency. Construction of a `MoneyInterface` from a product is the job of `ProductPriceService` (task 005). The schema is fully declared on the entity via attributes; no handwritten migration accompanies it.

## Context
- Related file: `packages/catalog/src/Entity/Product.php`
- Pattern reference for entity shape: `marko/admin-auth/src/Entity/AdminUser.php` (column attributes, public properties, `BelongsToMany` relationship). **Ignore** the AdminUser timestamps precedent — products do not have `created_at` / `updated_at` in this plan (deferred per `_plan.md` Out of Scope).
- Schema source: this entity's attributes are the only definition of the `products` table. Marko's `db:migrate` discovers it and generates the migration. There is no parallel SQL file in this package.
- Columns:
  - `public ?int $id = null` — `#[Column(primaryKey: true, autoIncrement: true)]`
  - `public string $sku` — `#[Column(length: 255, unique: true)]`. Carries no multi-store docblock (SKU is genuinely globally unique in this plan).
  - `public string $name` — `#[Column(length: 255)]`. Carries a `@todo multi-store` docblock noting that the stores module will scope this per store-view.
  - `public int $basePriceAmount` — `#[Column(type: 'BIGINT')]`. Stored as bigint minor units to fit JPY-scale prices etc. Use the uppercase type name `'BIGINT'` to match the schema-generator's documented usage in `Marko\Database\Tests\Feature\EntityToMigrationWorkflowTest` (which uses `'TEXT'` uppercased). Carries a `@todo multi-store` docblock noting that currency will become store-scoped; today the currency is resolved by `ProductPriceService` via the bound `CurrencyConfigInterface` default.
- Relationship:
  - `public array $categories = []` annotated `#[BelongsToMany(entityClass: Category::class, pivotClass: ProductCategory::class, foreignKey: 'productId', relatedKey: 'categoryId')]`. PHPDoc declares `@var array<Category>`.
- **No Money accessor / mutator.** Do not add `getBasePrice()` or `setBasePrice()` on the entity. Currency validation lives in `ProductService` (task 014) before persistence; Money construction for read-side consumers lives in `ProductPriceService` (task 005).
- Properties are public for hydrator access (matches admin-auth precedent).
- Schema-shape verification (column types, unique index on sku, BelongsToMany pivot wiring) lives in task 011's `SchemaIntegrationTest`.

## Requirements (Test Descriptions)
- [ ] `it maps to the products table via the Table attribute`
- [ ] `it exposes id as a nullable auto-increment primary key column`
- [ ] `it exposes sku as a unique string column with length 255`
- [ ] `it exposes name as a required string column with length 255`
- [ ] `it exposes basePriceAmount as a BIGINT column (uppercase type string)`
- [ ] `it declares a BelongsToMany categories relationship through ProductCategory with foreignKey productId and relatedKey categoryId`
- [ ] `it does not declare a getBasePrice or setBasePrice method on the entity`
- [ ] `it does not declare createdAt or updatedAt columns`
- [ ] `it carries multi-store refactor docblocks on name and basePriceAmount`
- [ ] `it can be constructed and have its public properties assigned directly`

## Acceptance Criteria
- File location: `packages/catalog/src/Entity/Product.php`.
- Tests in `packages/catalog/tests/Unit/Entity/ProductTest.php` assert attribute metadata via reflection (no PHPUnit mocks, no database). One of the tests is a negative assertion via reflection that `getBasePrice` / `setBasePrice` methods do not exist — this guards against the entity slipping back into being currency-aware in future edits.
- All multi-store-fragile spots have searchable `@todo multi-store` markers.
- Not `final`.
- `phpstan` clean at level 8.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
