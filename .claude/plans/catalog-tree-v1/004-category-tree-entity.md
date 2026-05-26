# Task 004: `CategoryTree` entity

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the `CategoryTree` entity representing a named navigation tree. Identity-only entity — does not implement `HasScopesInterface`. Carries the stable `code`, human label `name`, and `is_default` flag.

## Context
- Target file: `packages/catalog/src/Entity/CategoryTree.php`
- Namespace: `Markommerce\Catalog\Entity`
- Pattern to mirror: existing `Category` entity but WITHOUT `HasScopes` (this entity is not locale/market scoped)
- Schema (via attributes):
  - `id` — `#[Column(primaryKey: true, autoIncrement: true)] public ?int $id = null;`
  - `code` — `#[Column(length: 64, unique: true)] public string $code = '';`
  - `name` — `#[Column(length: 255)] public string $name = '';`
  - `is_default` — `#[Column(name: 'is_default')] public bool $isDefault = false;`
- Table name: `catalog_category_trees` (use `#[Table('catalog_category_trees')]`)
- Auto-migration derives schema from attributes; no migration file needed

## Requirements (Test Descriptions)
- [x] `it can be instantiated with default values`
- [x] `it exposes id, code, name, and isDefault public properties`
- [x] `it defaults isDefault to false`
- [x] `it defaults id to null until persisted`
- [x] `it is mapped to the catalog_category_trees table`

## Acceptance Criteria
- Entity file in correct location and namespace
- Test file in `packages/catalog/tests/Unit/Entity/CategoryTreeTest.php`
- No `final` keyword on the class
- PHPStan level 8 clean

## Implementation Notes
- Mirrored `#[Table]` and `#[Column]` attribute usage from `Category` entity exactly, omitting `HasScopes` and `HasScopesInterface` since `CategoryTree` is not locale/market scoped.
- Entity extends `Marko\Database\Entity\Entity` directly with no traits.
- The `is_default` column maps to `isDefault` property via `#[Column(name: 'is_default')]`.
- The pre-existing failure for `DuplicateDefaultTreeException` (task 002) was already present before this task and is unrelated.
