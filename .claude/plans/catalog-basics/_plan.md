# Plan: Catalog Module — Product & Category Basics

## Created
2026-05-12

## Status
completed

## Objective
Stand up the foundational `markommerce/catalog` module (Product entity, Category entity, product↔category many-to-many) on top of Marko's ORM, and introduce a swappable Money abstraction in `markommerce/core` so every commerce module that touches prices speaks to an interface instead of `moneyphp/money` directly.

## Related Issues
none

## Discovery Notes
- `markommerce/core` exists but is empty (just `composer.json` + `src/`). It stays empty after this plan — it's a placeholder for future cross-cutting primitives. The Money abstraction lives in its own packages (see below), per Marko's "one primitive per package" convention.
- **Money lives in two new packages**: `markommerce/money` (interface-only — `MoneyInterface`, `MoneyFactoryInterface`, `CurrencyConfigInterface`, `MoneyException`) and `markommerce/money-moneyphp` (driver — concrete `Money`, `CurrencyConfig`, `MoneyFactory`, plus `module.php` bindings). Catalog depends only on the interface package. The root metapackage installs the default driver. This matches the interface/driver split the architecture doc prescribes for payment/shipping, applied to money.
- Marko already ships a full ORM in `marko/database`: `Entity` base class, `Repository` base class (concrete methods: find/findOrFail/findAll/findBy/findOneBy/save/delete/insertBatch/matching/with/count/exists/existsBy), and the relationship attributes `#[Table]`, `#[Column]`, `#[BelongsToMany]`, `#[HasMany]`, `#[BelongsTo]`. The companion `RepositoryInterface` only exposes find/findOrFail/findAll/findBy/findOneBy/existsBy/save/delete/insertBatch — `matching`, `with`, `count`, `exists` are concrete-only and not contract-guaranteed. `BelongsToMany` requires a concrete pivot Entity class. Marko Entity metadata only supports single-column primary keys (`EntityMetadata::$primaryKey` is a single string), so pivot tables need a surrogate auto-increment id even when a composite unique index would otherwise suffice — see `marko/admin-auth/src/Entity/RolePermission.php` for the precedent.
- **Marko has entity-driven schema generation.** `Marko\Database\Entity\SchemaBuilder` converts `EntityMetadata` to a `Table` schema object, and `Marko\Database\Migration\MigrationGenerator` writes migration files from a `SchemaDiff`. The `db:migrate` CLI command runs the diff-and-generate flow automatically. **We do not write SQL migration files by hand.** All tables, columns, indexes, and foreign keys are declared on the entities via `#[Table]`, `#[Column]` (with `references`/`onDelete`/`onUpdate`), `#[Index]`, and `#[ForeignKey]`. (Note: `marko/admin-auth` predates this generator and has handwritten migrations — we do **not** follow that part of its precedent.)
- **Marko has no built-in abstraction for `created_at`/`updated_at`.** `marko/admin-auth/AdminUser` declares them as plain `?string` columns and relies on the caller to set them — there is no trait, observer, or repository hook that maintains them. Per the user's directive, the catalog entities **do not** include `created_at`/`updated_at` in this plan. They can be added later as part of a generic timestamps abstraction in `marko/database`, not catalog.
- `marko/admin-auth` is the precedent module for: entity/repository class shape (entities extend `Marko\Database\Entity\Entity`, repositories extend `Marko\Database\Repository\Repository` with `protected const string ENTITY_CLASS = Foo::class`) and module-bindings (`*RepositoryInterface => *Repository` in `module.php`). It is **not** the precedent for schema definition — we use the entity-attribute path described above.
- The architecture doc says "Models/ — Domain models (not Eloquent — plain PHP)" but the user has elected to follow Marko ORM (matching the admin-auth precedent for entity/repo shape). The doc will read inconsistent until a future cleanup; not part of this plan.
- `MarkoException` lives at `Marko\Core\Exceptions\MarkoException` with the `message/context/suggestion` triplet.
- `moneyphp/money` is not yet in any lockfile — will be added to `markommerce/money-moneyphp` (driver package only). `ext-intl` is required at that same driver level for `Money::format()` localized output. Neither dependency leaks into the interface package or into catalog.

### Addendum (post-merge, code review feedback)
Tasks 019 and 020 were appended after the initial 18-task plan completed and the PR was opened. Code review surfaced that `CategoryAssignmentService` (task 016) directly injects `ConnectionInterface` / `TransactionInterface` and executes raw SQL — a violation of the architecture rule "Application code never touches database classes directly". The fix moves the pivot SQL into a dedicated `ProductCategoryRepository` (task 019), then refactors the service to consume the repo (task 020). After 020, no catalog service references a database class.

### Decisions resolved during clarification
- **Entity layer**: Marko ORM (extend `Entity`, repos extend `Repository`).
- **Schema definition**: Entities define the full schema via `#[Column]` / `#[Index]` / `#[ForeignKey]` attributes. No handwritten migration files in this package. Migration files are generated and run by Marko's `db:migrate` against a consuming application.
- **Money abstraction (interface/driver split)**: `markommerce/money` (interface) defines `MoneyInterface`, `MoneyFactoryInterface`, `CurrencyConfigInterface`, and `MoneyException` — and nothing else. `markommerce/money-moneyphp` (driver) ships the default implementations backed by `moneyphp/money`, plus a `module.php` that binds `MoneyFactoryInterface` and `CurrencyConfigInterface` to its defaults. `Money::multiply(string $factor)` takes a numeric string (precision-preserving, matches moneyphp's contract). Catalog (and every other commerce module) depends only on the interface package; constructing Money instances goes through `MoneyFactoryInterface::create(int $amount, ?string $currency = null)` so consumers never reference the driver class directly. `ext-intl` is a hard requirement of the driver package only.
- **Currency scope**: Single hardcoded default currency (`'USD'`) exposed via `CurrencyConfigInterface` and the driver's default `CurrencyConfig`. Multi-currency / store-scope refactor deferred. The interface lives in `markommerce/money`; the default binding lives in `markommerce/money-moneyphp`'s `module.php`.
- **Entity stays currency-unaware**: `Product` stores only `basePriceAmount` (int minor units) and is **not** aware of currency. It exposes no Money accessor/mutator. Construction of `Money` from a product is the job of a dedicated `ProductPriceService` in `markommerce/catalog` (future home for discount / tax / store-currency logic). The service injects `MoneyFactoryInterface`, not a concrete class.
- **No timestamps**: products and categories do not have `created_at` / `updated_at` columns in this plan. Marko has no abstraction yet, and the user does not want to set/maintain them by hand. They are not in scope.
- **Service layer**: Both repositories AND services are public. `ProductService` + `CategoryService` for CRUD; separate `CategoryAssignmentService` for product↔category attach/detach; separate `ProductPriceService` for product → Money. Cross-module callers consume services by convention.
- **No service-level `*OrFail` methods**: collapse the redundancy with the repository's inherited `findOrFail`. Services expose `get(): ?Entity`; callers needing throw-on-missing call `$repo->findOrFail($id)` directly. Internal service operations (e.g. `delete`) still raise the catalog-specific `*NotFoundException` so the catalog API surface throws catalog exceptions, not `RepositoryException`.
- **CI runs all tests**: the project's CI invokes `composer test:all` (not `composer test`), so feature/integration tests tagged `integration-destructive` are not optional. Tests that need a real database go in **the monorepo root** `tests/Feature/` (NOT inside `packages/catalog/`) and are tagged with the group. Keeping the DB-bound tests at the root keeps the catalog package itself free of any DB-driver coupling, even at dev-time. The root `phpunit.xml` testsuite includes both `packages/*/tests` and `tests/`.

### Multi-store refactor-later markers
The following will carry explicit `@todo multi-store` docblocks so the future stores/config module knows what to revisit:
- `Product::$name` — scope-aware in the future
- `Product::$basePriceAmount` — currency will become store-scoped; for now `ProductPriceService` resolves it via the bound `MoneyFactoryInterface` which uses the driver's default currency
- `Category::$name` — scope-aware in the future
- `CurrencyConfigInterface` declaration in `markommerce/money` and its default binding in `markommerce/money-moneyphp/module.php`
- `ProductPriceService::getBasePrice` — currency-resolution path is the seam where store scope plugs in

## Scope

### In Scope
- New `markommerce/money` interface package — exports `MoneyInterface` (amount, currency, `add`, `subtract`, `multiply(string $factor)`, `allocate`, `equals`, `greaterThan`, `lessThan`, `isZero`, `format(?string $locale = null)`), `MoneyFactoryInterface` (`create(int $amount, ?string $currency = null)`), `CurrencyConfigInterface` (`getDefault(): string`), and `MoneyException`. No moneyphp, no `ext-intl`, no concrete classes.
- New `markommerce/money-moneyphp` driver package — concrete `Money` (backed by `moneyphp/money`), default `CurrencyConfig` returning `'USD'`, `MoneyFactory`, plus `module.php` binding `MoneyFactoryInterface` and `CurrencyConfigInterface` to those defaults. `moneyphp/money` and `ext-intl` are hard requirements of this package only.
- New `markommerce/catalog` package (composer.json with `extra.marko.module=true`, autoload, directory scaffold). Depends on `markommerce/money` interface package — **never** on the driver.
- `Product` entity (id, sku, name, basePriceAmount). **No timestamps, no Money accessor on the entity itself.** Multi-store docblock on `name`. Currency-unaware.
- `Category` entity (id, name) with multi-store docblock on `name`.
- `ProductCategory` pivot entity (id, productId, categoryId — surrogate PK, unique composite index) for Marko's `BelongsToMany`.
- **Schema declared entirely via `#[Table]` / `#[Column]` / `#[Index]` / `#[ForeignKey]` attributes on the three entities.** No handwritten migration files in this package — applications generate them via `db:migrate`.
- `ProductRepositoryInterface` + `ProductRepository` (adds `findBySku`).
- `CategoryRepositoryInterface` + `CategoryRepository`.
- Catalog exceptions (`ProductNotFoundException`, `CategoryNotFoundException`, `DuplicateSkuException`, `InvalidProductDataException`, `InvalidCategoryDataException`).
- Catalog domain events (`ProductCreated`, `ProductUpdated`, `ProductDeleted`, `CategoryCreated`, `CategoryUpdated`, `CategoryDeleted`, `ProductAssignedToCategory`, `ProductRemovedFromCategory`).
- `ProductPriceServiceInterface` + `ProductPriceService` — given a `Product`, returns a `MoneyInterface` constructed via `CurrencyConfigInterface`. The seam where future discount / tax / store-currency logic plugs in.
- `ProductServiceInterface` + `ProductService` (create/get/getBySku/update/delete/list — no `*OrFail` methods at service level). Validates currency on incoming `MoneyInterface`, extracts the minor-unit amount, dispatches events.
- `CategoryServiceInterface` + `CategoryService` (create/get/update/delete/list — no `*OrFail` methods at service level). Dispatches events.
- `CategoryAssignmentServiceInterface` + `CategoryAssignmentService` (assign/unassign/getCategoriesForProduct/getProductsInCategory, transactional, dispatches events).
- `module.php` with all interface→implementation bindings (including `ProductPriceServiceInterface`).
- README for `markommerce/catalog` and `markommerce/core`.

### Out of Scope
- Stores / multi-store config module (separate future plan).
- Multi-currency support (deferred — single default currency only).
- `created_at` / `updated_at` columns on products and categories (no Marko abstraction yet; deferred until one exists in `marko/database`).
- Category tree / hierarchy / parent_id.
- Product attributes / variants / images / SEO / inventory / stock.
- Product status / enabled flag / soft delete.
- Search, filtering, pagination beyond what `Repository::matching()` already provides.
- Admin UI / API endpoints / controllers.
- Caching of products or categories.
- Reverse-relationship eager loading from `Category` to products (one-way is enough for now).
- Handwritten SQL migration files (schema comes from entity attributes; the consuming application's `db:migrate` generates and runs migrations).

## Success Criteria
- [ ] `composer test:all` passes for both `packages/core` and `packages/catalog` (Pest 4, parallel — includes feature/integration tests).
- [ ] Test coverage ≥ 80% in both packages.
- [ ] `phpstan analyse` passes at level 8.
- [ ] `phpcs` and `php-cs-fixer` both clean.
- [ ] Money value object covers add/subtract/multiply(string)/allocate/equals/compare/isZero/format with currency-mismatch errors.
- [ ] A product can be created, retrieved by id and by SKU, updated, deleted, and listed.
- [ ] A category can be created, retrieved, updated, deleted, and listed.
- [ ] A product can be assigned to multiple categories and the assignment can be queried in both directions.
- [ ] `ProductPriceService::getBasePrice(Product)` returns a `MoneyInterface` constructed using the configured default currency.
- [ ] Every domain operation that mutates state dispatches the appropriate domain event when an event dispatcher is bound.
- [ ] `module.php` registers all interface↔implementation bindings (including `ProductPriceServiceInterface`).
- [ ] All cross-module references in code go through interfaces (no direct `moneyphp/money` use outside `markommerce/money-moneyphp`'s driver package, no direct `Marko\Database\Repository\Repository` consumption outside catalog's own repositories).
- [ ] No SQL migration files exist under `packages/catalog/database/migrations/` — schema lives on the entities.

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Scaffold `markommerce/money` interface pkg + `MoneyInterface` + `MoneyFactoryInterface` + `CurrencyConfigInterface` | - | pending |
| 002 | Create `MoneyException` in `markommerce/money` | 001 | pending |
| 003 | Scaffold `markommerce/money-moneyphp` driver pkg + default `Money` impl backed by moneyphp | 001, 002 | pending |
| 004 | `markommerce/money-moneyphp`: default `CurrencyConfig` (USD) + `MoneyFactory` + `module.php` bindings | 003 | pending |
| 005 | Scaffold `markommerce/catalog` package (depends on `markommerce/money` interface only — no SQL migrations directory) | - | pending |
| 006 | Create `Category` entity (schema via attributes) | 005 | pending |
| 007 | Create `ProductCategory` pivot entity (schema via attributes with FKs + class-level unique `#[Index]`) | 005, 006 | pending |
| 008 | Create `Product` entity with `BelongsToMany` categories — schema via attributes, no Money accessor, no timestamps | 005, 007 | pending |
| 009 | Create catalog exception classes (incl. `InvalidProductDataException::currencyMismatch`) | 005 | pending |
| 010 | Create `CategoryRepositoryInterface` + `CategoryRepository` | 006 | pending |
| 011 | Create `ProductRepositoryInterface` + `ProductRepository` (findBySku); schema integration test lives at monorepo root | 008 | pending |
| 012 | Create catalog domain events | 006, 008 | pending |
| 013 | Create `ProductPriceServiceInterface` + `ProductPriceService` (delegates to `MoneyFactoryInterface`) | 001, 008 | pending |
| 014 | Create `CategoryServiceInterface` + `CategoryService` (no `*OrFail` methods) | 009, 010, 012 | pending |
| 015 | Create `ProductServiceInterface` + `ProductService` (no `*OrFail`; validates currency, extracts amount) | 001, 009, 011, 012 | pending |
| 016 | Create `CategoryAssignmentServiceInterface` + `CategoryAssignmentService` | 009, 010, 011, 012 | pending |
| 017 | Wire up `module.php` bindings for catalog (Repos, ProductPriceService, Services, AssignmentService) | 013, 014, 015, 016 | pending |
| 018 | Write READMEs for `markommerce/money`, `markommerce/money-moneyphp`, and `markommerce/catalog` | 017 | pending |
| 019 | **Addendum** — Create `ProductCategoryRepository` (extract pivot SQL out of the service) | 007, 011 | pending |
| 020 | **Addendum** — Refactor `CategoryAssignmentService` to use `ProductCategoryRepository` (close architecture violation) | 019 | pending |

## Architecture Notes

### Money — interface/driver split across two packages
- **`markommerce/money` (interface package, no concretes)** exposes:
  - `Markommerce\Money\MoneyInterface` — value-object contract. Methods that produce new Money values return `MoneyInterface` (not `self`) so a Preference can swap the implementation across the system without breaking type compatibility.
  - `Markommerce\Money\MoneyFactoryInterface::create(int $amount, ?string $currency = null): MoneyInterface` — single service-bound entry point for constructing Money. Consumers (catalog, etc.) inject this interface and never `new` a concrete Money class. When `$currency` is null, the implementation uses the configured default.
  - `Markommerce\Money\CurrencyConfigInterface::getDefault(): string` — returns the application's default ISO 4217 code.
  - `Markommerce\Money\MoneyException` — extends `MarkoException`. Named factories for `currencyMismatch`, `invalidAllocationRatios`, `invalidMultiplyFactor`.
- **`markommerce/money-moneyphp` (driver package)** exposes (driver namespace nested under the contract package's namespace per the `Marko\Database\MySql\…` precedent in `marko/database-mysql`):
  - `Markommerce\Money\Moneyphp\Money` — `readonly class` implementing `MoneyInterface`, wraps a private `Money\Money` from moneyphp. `multiply(string $factor)` is precision-preserving. `format(?string $locale)` uses moneyphp's `IntlMoneyFormatter` (intl required).
  - `Markommerce\Money\Moneyphp\CurrencyConfig` — implements `CurrencyConfigInterface::getDefault()` returning `'USD'`. Carries the `@todo multi-store` docblock.
  - `Markommerce\Money\Moneyphp\MoneyFactory` — implements `MoneyFactoryInterface`. Injects `CurrencyConfigInterface` and resolves the default at every `create()` call (not cached at construction time), so a future rebinding of `CurrencyConfigInterface` to a request-/store-scoped resolver works without each consumer rebuilding its factory.
  - `module.php` binds `CurrencyConfigInterface => CurrencyConfig` and `MoneyFactoryInterface => MoneyFactory`. Hard requires `moneyphp/money` and `ext-intl`.
- Every other markommerce module depends only on `markommerce/money` (the interface package) — the driver is wired into the application by the root metapackage's composer require.

### Catalog entity / repository / service split
- Entities extend `Marko\Database\Entity\Entity` and use `#[Table]` + `#[Column]` + `#[BelongsToMany]` attributes. Public properties (per Marko Entity convention — see `AdminUser`).
- Repositories extend `Marko\Database\Repository\Repository<TEntity>` with `protected const string ENTITY_CLASS = …`. Their interfaces extend `Marko\Database\Repository\RepositoryInterface<TEntity>` and add entity-specific extras (`findBySku` for Product). The parent interface guarantees `find`, `findOrFail`, `findAll`, `findBy`, `findOneBy`, `existsBy`, `save`, `delete`, `insertBatch`. Methods such as `count`, `exists`, `with`, `matching` exist on the concrete `Repository` only — services that need them must call the concrete class or re-declare those methods on the catalog-specific interface. The plan does not require `with`/`matching` through the interface anywhere.
- Services depend on repository interfaces, never concretes. They own validation, transactions, and `EventDispatcherInterface` calls. The event dispatcher is optional (nullable) so unit tests don't need to wire one up — matching admin-auth.

### Domain events vs. ORM lifecycle events
The plan dispatches both `Markommerce\Catalog\Event\Product*` events (from services) and the `Marko\Database\Events\Entity*` events (automatically fired by `Repository::save`/`delete`). This is deliberate, not duplication:
- ORM-level events fire for every entity save, regardless of context, and carry the generic `Entity` payload. They're useful for cross-cutting concerns: audit logging, caching, query invalidation.
- Catalog domain events fire only after the service-layer validations succeed, and carry the domain-meaningful entity (`Product`/`Category`) plus richer semantics (e.g. `ProductAssignedToCategory(int $productId, int $categoryId)` — no entity-level analogue).
Observers and plugins should subscribe to whichever layer matches their semantic need.

### Shared test fakes
Service tests (013, 014, 015, 016) reuse hand-written fakes living in `packages/catalog/tests/Support/`, autoloaded via the `Markommerce\Catalog\Tests\` autoload-dev namespace seeded by task 005's scaffolding. Required fakes:
- `FakeProductRepository`, `FakeCategoryRepository` — implement the catalog repository interfaces (introduced incrementally by the service tasks that need them).
- `FakeConnection` — implements `Marko\Database\Connection\ConnectionInterface` + `TransactionInterface` (introduced in task 011).
- `FakeCurrencyConfig` — returns a configurable ISO 4217 string (used by ProductService for currency-mismatch tests).
- `FakeMoney` — implements `MoneyInterface` as a **minimal stub**. Only `amount()`, `currency()`, `equals()`, and `isZero()` carry real behaviour (constructor takes amount + currency). The arithmetic / comparison / formatting methods (`add`, `subtract`, `multiply`, `allocate`, `greaterThan`, `lessThan`, `format`) throw `\LogicException('not implemented in FakeMoney')`. This is intentional: a "real" hand-rolled arithmetic fake would parallel-implement money math and could diverge from the driver's behaviour. Service tests never need that math — they only read amount and currency off the value object. **Does not** require the driver package to be installed. Used by service tests so catalog stays driver-independent.
- `FakeMoneyFactory` — implements `MoneyFactoryInterface`, returns `FakeMoney` instances; records calls. Used by `ProductPriceService` tests.
- `RecordingEventDispatcher` — captures dispatched events.
Each service task is responsible for adding the specific fake it needs (one fake per task author, no duplication).

### Risk-free composer install
- `markommerce/markommerce` root composer requires: `markommerce/core`, `markommerce/money`, `markommerce/money-moneyphp`, `markommerce/catalog`. Catalog's own composer requires only `markommerce/money` (interface) and `marko/database` / `marko/core`. Strict no-driver-dependency for catalog is enforced by the package-structure tests in task 005.

### Pivot table & many-to-many
- `products` and `categories` are plain tables with surrogate `id INT UNSIGNED AUTO_INCREMENT`.
- `product_categories` has a surrogate `id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`, two FK columns (`product_id`, `category_id`) with `ON DELETE CASCADE`, and a `UNIQUE INDEX (product_id, category_id)` to enforce assignment uniqueness at the DB layer. The surrogate id is a Marko ORM constraint (single-column PKs only) — it has no domain meaning. Matches the `marko/admin-auth/src/Entity/RolePermission` precedent.
- A `ProductCategory` entity exists solely to satisfy Marko's `BelongsToMany` requirement (`pivotClass` must be an Entity class).
- All three tables' schemas live on the entities via `#[Table]`, `#[Column]` (with `references`/`onDelete`), `#[Index]`, and `#[ForeignKey]` attributes. Marko's `db:migrate` discovers entities and generates ordered migration files automatically (parent tables before pivots, by FK dependency).

### ProductPriceService — the price-resolution seam
- `Markommerce\Catalog\Service\ProductPriceServiceInterface::getBasePrice(Product $product): MoneyInterface` — the single cross-module entry point for "what is this product's price?". Construction of `Money` from `$product->basePriceAmount` happens here, not on the entity.
- Default `ProductPriceService` implementation injects `MoneyFactoryInterface` (from `markommerce/money`) and returns `$moneyFactory->create($product->basePriceAmount)` — passing no currency so the factory uses the configured default. Catalog does **not** depend on the driver package; the abstraction is what makes this possible.
- This is intentionally where future logic for store-scoped currency, discounts, tier pricing, and tax inclusion will plug in — by either swapping the binding (Preference) or wrapping with a Plugin/Observer.

### Refactor-later docblocks
Every multi-store-fragile spot gets a `@todo multi-store` PHPDoc with a sentence explaining what changes when the stores/config module lands. The docblock is grep-able so the future plan can find every site to update.

## Risks & Mitigations
- **moneyphp/money is a hard runtime dependency of the default driver only.** Mitigation: `MoneyInterface` (in `markommerce/money`, interface-only) keeps the dependency contained within `markommerce/money-moneyphp`; downstream replacement just rebinds `MoneyFactoryInterface` and `CurrencyConfigInterface` in `module.php` and swaps the driver package. Catalog never depends on the driver.
- **Default-currency primitive is a hidden global.** A hardcoded `'USD'` will leak into every product's display. Mitigation: expose it through an interface (`CurrencyConfigInterface::getDefault()`), bind it in `module.php` to a default-returning class, and PHPDoc it as the multi-store seam. Stores/config module will rebind it later.
- **Marko's `BelongsToMany` requires a concrete pivot Entity class** — easy to forget. Mitigation: explicit task (`007`) so the implementer doesn't try to use the attribute against a non-existent class.
- **Service-vs-repository boundary slips over time** (modules start calling repositories for convenience). Mitigation: the catalog README states the convention explicitly so future plans inherit it.
- **Architecture doc inconsistency** ("plain PHP" vs. Marko Entity) may confuse future implementers. Mitigation: noted in Discovery Notes; out of scope for this plan but flagged.
- **Test coverage on infrastructure code is awkward** (entities are mostly data; repositories need a database). Mitigation: services own the meaningful logic and are unit-testable with fakes (`FakeProductRepository`, etc.). The catalog schema integration test runs against the real database from the **monorepo root** `tests/Feature/Schema/CatalogSchemaIntegrationTest.php` under the `integration-destructive` group, and CI runs `composer test:all` so it always executes. Catalog itself remains DB-driver-free.
- **Entity-driven schema means a missing attribute silently drops a column from the generated migration.** Mitigation: each entity task includes attribute-presence assertions via reflection, and a feature test runs `db:migrate` against the test database and asserts table shape via `INFORMATION_SCHEMA`. The feature test catches missing `#[Index]` / `#[ForeignKey]` declarations that pure unit tests would miss.
