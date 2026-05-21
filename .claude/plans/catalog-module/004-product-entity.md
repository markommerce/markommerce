# Task 004: Product Entity

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the `Product` entity: an auto-increment primary key, a globally-unique `sku`, and locale-scoped `name` and `description` properties.

## Context
- Create at `packages/catalog/src/Entity/Product.php`.
- Extends `Marko\Database\Entity\Entity`, `implements HasScopesInterface`, and `use HasScopes` (from `markommerce/scope`).
- Mapped to table `catalog_products` via `#[Table('catalog_products')]`.
- Properties:
  - `id`: `#[Column(primaryKey: true, autoIncrement: true)] public ?int $id = null;`
  - `sku`: `#[Column(length: 64, unique: true)] public string $sku = '';` — the unique flag produces a unique DB index. SKU is global (NOT scoped).
  - `name`: `#[Column(length: 255)] #[Scoped(axes: ['locale'])] public string $name = '';`
  - `description`: `#[Column(type: 'text', nullable: true)] #[Scoped(axes: ['locale'])] public ?string $description = null;`
- The `HasScopes` trait adds the `scopes` JSON column automatically — do not declare it manually.
- Reference pattern: the `Product` example in `docs/src/content/docs/packages/scope.md`.
- No `final`. `declare(strict_types=1);`.

## Requirements (Test Descriptions)
- [x] `it maps the Product entity to the catalog_products table`
- [x] `it exposes an auto-increment integer primary key id`
- [x] `it declares the sku column as unique`
- [x] `it does not mark the sku property as scoped`
- [x] `it marks the name property as scoped on the locale axis`
- [x] `it marks the description property as scoped on the locale axis`
- [x] `it implements HasScopesInterface and exposes a scopes storage column`

## Acceptance Criteria
- All requirements have passing tests
- Entity attributes are parseable by `EntityMetadataFactory`
- Code follows code standards

## Implementation Notes
- Entity created at `packages/catalog/src/Entity/Product.php` following the Category entity pattern.
- Extends `Marko\Database\Entity\Entity`, implements `HasScopesInterface`, and uses `HasScopes` trait.
- `sku` is declared with `unique: true` and `length: 64` — no `Scoped` attribute (globally unique).
- `name` and `description` are both scoped on the `locale` axis via `#[Scoped(axes: ['locale'])]`.
- `scopes` column comes automatically from the `HasScopes` trait — not declared manually.
- All 7 tests pass; full catalog suite (26 tests) passes.
