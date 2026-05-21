# Task 003: Category Entity

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the `Category` entity: an auto-increment primary key and locale-scoped `name` and `description` properties. Categories are flat — no parent/child hierarchy.

## Context
- Create at `packages/catalog/src/Entity/Category.php`.
- Extends `Marko\Database\Entity\Entity`, `implements HasScopesInterface`, and `use HasScopes` (from `markommerce/scope`).
- Mapped to table `catalog_categories` via `#[Table('catalog_categories')]`.
- Properties:
  - `id`: `#[Column(primaryKey: true, autoIncrement: true)] public ?int $id = null;`
  - `name`: `#[Column(length: 255)] #[Scoped(axes: ['locale'])] public string $name = '';`
  - `description`: `#[Column(type: 'text', nullable: true)] #[Scoped(axes: ['locale'])] public ?string $description = null;`
- The `HasScopes` trait adds the `scopes` JSON column automatically — do not declare it manually.
- Reference pattern: the `Product` example in `docs/src/content/docs/packages/scope.md` and `packages/scope/src/Storage/HasScopes.php`.
- No `final`. `declare(strict_types=1);`.

## Requirements (Test Descriptions)
- [x] `it maps the Category entity to the catalog_categories table`
- [x] `it exposes an auto-increment integer primary key id`
- [x] `it marks the name property as scoped on the locale axis`
- [x] `it marks the description property as scoped on the locale axis`
- [x] `it implements HasScopesInterface`
- [x] `it exposes a scopes storage column via the HasScopes trait`

## Acceptance Criteria
- All requirements have passing tests
- Entity attributes are parseable by `EntityMetadataFactory`
- Code follows code standards

## Implementation Notes
- Created `packages/catalog/src/Entity/Category.php` extending `Marko\Database\Entity\Entity`, implementing `HasScopesInterface`, and using the `HasScopes` trait.
- Created test file `packages/catalog/tests/Unit/Entity/CategoryTest.php` with 6 reflection-based tests verifying attributes and interface.
- All 6 requirements pass; full suite (734 tests) remains green.
