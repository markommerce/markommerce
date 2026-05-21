# Plan: Basic Catalog Module

## Created
2026-05-21

## Status
completed

## Objective
Create a basic `markommerce/catalog` package with Product and Category entities (globally-unique SKU, locale-scoped name/description), product-to-category assignment, a storefront controller that lists a category's products, and a seeder for fake test data.

## Related Issues
none

## Discovery Notes
- **Greenfield package** — `packages/catalog/` does not exist. Built per the user's decision as a *single* package `markommerce/catalog` containing both the domain layer and the storefront controller (no separate frontend package).
- **Scoping** — `markommerce/scope` provides `#[Scoped(axes: ['locale'])]` for entity properties plus the `HasScopes` trait, which adds a JSON `scopes` column. `ScopeResolver::resolved()` returns the locale-resolved value; `ScopeResolutionMiddleware` sets the active locale per HTTP request. `Product`/`Category` use `HasScopes` directly and implement `HasScopesInterface` (per the pattern documented in `docs/.../packages/scope.md`).
- **Persistence** — `marko/database` provides an abstract `Repository` (find/save/delete/findBy/matching/insertBatch); concrete repositories only declare `ENTITY_CLASS`. Entities use `#[Table]`/`#[Column]` attributes. No DB-driver-specific code is needed in catalog.
- **Seeders** — `marko/database` discovers classes marked `#[Seeder]` in a `Seed/` directory; `SeederInterface::run()` does the work.
- **Routing** — `marko/routing` supports `{id}` path parameters; the `Router` casts a `{id}` route param to an `int $id` controller-action argument.
- **Storefront precedent** — `packages/frontend-demo` shows the controller/Latte-view pattern (`#[Get]` route attributes, depends on `markommerce/frontend` + `markommerce/theme-blank`).

### Resolved during clarification
- **Single package** — domain + storefront controller all in `markommerce/catalog`.
- **SKU uniqueness** — enforced by BOTH a unique DB index AND a service-layer pre-check that throws `DuplicateSkuException` ("loud errors").
- **Flat categories** — no parent/child hierarchy.
- **Locale overrides in the seeder, but `config/scope.php` is left untouched.** The seeder writes locale-scoped overrides (e.g. `locale:de`, `locale:fr`) directly. These locales are intentionally NOT registered in `config/scope.php` — the user will add them temporarily when testing resolution end-to-end. This forces an important constraint: the seeder MUST write overrides via the entity's `HasScopes::setOverride()` method directly, NOT via `ScopeResolver::setOverride()` (the latter runs `ScopeSignatureValidator`, which rejects locale paths absent from the configured hierarchy).

## Scope

### In Scope
- `markommerce/catalog` package scaffold (composer.json, module.php, Pest setup).
- `Product` entity: auto-increment id, globally-unique `sku`, locale-scoped `name` + `description`.
- `Category` entity: auto-increment id, locale-scoped `name` + `description` (flat — no hierarchy).
- `ProductCategoryAssignment` pivot entity linking products and categories.
- Repository contracts (interfaces) + concrete repository implementations + in-memory test fakes.
- `ProductService` — creates products, enforces SKU uniqueness via `DuplicateSkuException`.
- `CategoryAssignmentService` — assign/detach a product to/from a category, list a category's products.
- Catalog domain exceptions extending `MarkoException`.
- Storefront `CategoryController` — `GET /catalog/category/{id}` lists the category's products; 404 for an unknown category.
- `CatalogSeeder` — generates fake categories, products, assignments, and locale-scoped overrides.
- Package `README.md`.

### Out of Scope
- Pricing, inventory, product images, variants, attributes.
- Nested/hierarchical categories.
- Editing `config/scope.php` to register concrete locale scopes (user does this manually when testing).
- Cart/checkout integration.
- Admin UI for managing products/categories.
- A separate frontend/theme package (everything lives in `markommerce/catalog`).

## Success Criteria
- [ ] `markommerce/catalog` package exists and is a discoverable Marko module.
- [ ] Products and categories carry locale-scoped name/description via `#[Scoped]` + `HasScopes`.
- [ ] Creating a product with a duplicate SKU throws `DuplicateSkuException`.
- [ ] Products can be assigned to and detached from categories.
- [ ] `GET /catalog/category/{id}` returns the category's products (200) or 404 for an unknown id.
- [ ] `CatalogSeeder` produces fake products, categories, assignments, and locale overrides.
- [ ] All tests passing
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding | - | completed |
| 002 | Catalog domain exceptions | 001 | completed |
| 003 | Category entity | 001 | completed |
| 004 | Product entity | 001 | completed |
| 005 | ProductCategoryAssignment pivot entity | 003, 004 | completed |
| 006 | Repository contracts + in-memory fakes | 003, 004, 005 | completed |
| 007 | Repository implementations | 006 | completed |
| 008 | ProductService (SKU uniqueness) | 002, 006 | completed |
| 009 | CategoryAssignmentService | 002, 006 | completed |
| 010 | module.php interface bindings | 007 | completed |
| 011 | Storefront CategoryController + view | 008, 009 | completed |
| 012 | CatalogSeeder | 007, 008, 009 | completed |
| 013 | Package README | 001-012 | completed |

## Architecture Notes
- **Single package, layered internally**: `src/Contracts/` (repository interfaces), `src/Entity/` (marko/database entities), `src/Repositories/`, `src/Services/`, `src/Exceptions/`, `src/Controller/`, `resources/views/`, AND `Seed/` at the PACKAGE ROOT (a sibling of `src/`, not inside it). `marko/database`'s `SeederDiscovery` globs `vendor/*/*/Seed` while `EntityDiscovery` globs `vendor/*/*/src/Entity` — the two discovery mechanisms deliberately use different layouts. The `Seed/` directory therefore needs its own PSR-4 autoload entry (`Markommerce\Catalog\Seed\` → `Seed/`). `architecture.md` was updated to name an `Entity/` directory (was `Models/`), consistent with marko/database's `EntityDiscovery`, which globs `src/Entity`.
- **Table names** are prefixed: `catalog_products`, `catalog_categories`, `catalog_product_category`.
- **Scoped properties**: `name` and `description` on both entities are `#[Scoped(axes: ['locale'])]`. Entities `use HasScopes` and `implements HasScopesInterface` directly (no companion entity).
- **Repository pattern**: services depend only on `*RepositoryInterface`; concrete repositories extend `Marko\Database\Repository\Repository`. Unit tests use hand-written in-memory fakes (see `testing.md`).
- **module.php** declares only interface→implementation `bindings`. Entities, routes, and seeders are auto-discovered by Marko — no manual registration.
- **Exceptions** extend `MarkoException` with `message`/`context`/`suggestion` and static factory methods (see `packages/scope/src/Exceptions/`).
- **Seeder override-write path**: write locale overrides with `$entity->setOverride('locale:de', 'name', $value)` (the `HasScopes` trait method) — never `ScopeResolver::setOverride()`, which validates the signature against the configured hierarchy and would reject unconfigured locales. Confirmed against `packages/scope/config/scope.php`: the shipped config registers ONLY `locale:default`, so `de`/`fr` are genuinely unconfigured and `ScopeResolver::setOverride()` would throw `InvalidSignatureForAttributeException`. The `HasScopes::setOverride()` path is the correct choice.
- **`ScopeResolver` has no interface and four dependencies**: the storefront controller (task 011) injects the concrete `Markommerce\Scope\Resolver\ScopeResolver`. At runtime the scope module's `module.php` registers it as a singleton (autowired). In controller tests it must be built manually via the full scope stack — see task 011 and `packages/scope/tests/Feature/DefaultScopeResolutionTest.php::buildResolverStack()`.

## Risks & Mitigations
- **Branch base**: `feature/catalog-module` is branched off `feature/scope-resolution-pipeline` (unmerged), because catalog depends on the scope module on that branch. Mitigation: the catalog PR should target `develop` and may need a rebase once the scope branch merges.
- **Seeder overrides for unconfigured locales**: using `ScopeResolver::setOverride()` would throw `InvalidSignatureForAttributeException`. Mitigation: task 012 mandates the direct `HasScopes::setOverride()` write path and documents it.
- **`DefaultScopeGuard` in seeder tests**: `HasScopes::setOverride()` calls `DefaultScopeGuard::assertWritable()`. Mitigation: seeder tests must configure the guard (`DefaultScopeGuard::configure(['locale' => 'default'])`) in setup and `reset()` afterward.
- **Repository tests without a live DB**: concrete repositories need a `ConnectionInterface`. Mitigation: use a fake/logging connection (see `packages/scope/tests/Feature/ScopedOverridesPersistenceTest.php`) for SQL-shape assertions; tag any real-DB round-trip tests `integration-destructive`.
- **Controller rendering approach**: direct Latte render returning a `Response` (vs the heavier Layout/Component attribute system used by `frontend-demo`). Mitigation: task 011 specifies the direct-render approach for simplicity; revisit at review if layout consistency is required.
