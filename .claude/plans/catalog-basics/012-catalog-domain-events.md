# Task 012: Create catalog domain events

**Status**: completed
**Depends on**: 006, 008
**Retry count**: 0

## Description
Define the catalog domain events that services will dispatch. Each event extends `Marko\Core\Event\Event` and holds the affected entity (or, for assignment events, both ids). They will be dispatched by services via `Marko\Core\Event\EventDispatcherInterface::dispatch(Event $event)`.

## Context
- Location: `packages/catalog/src/Event/`
- Pattern reference: `marko/admin-auth/src/Events/AdminUserCreated.php` — `class … extends Marko\Core\Event\Event` with a constructor using `readonly` promoted properties.
- **Important**: events MUST extend `Marko\Core\Event\Event` because `EventDispatcherInterface::dispatch` is typed `Event $event`. The classes cannot be declared `readonly class` because `Event` has a mutable `$propagationStopped` property (verified in `marko/core/src/Event/Event.php`). Use `readonly` on individual promoted properties instead.
- Events to create (one file each):
  - `ProductCreated(Product $product)`
  - `ProductUpdated(Product $product)`
  - `ProductDeleted(int $productId)` — entity is gone by the time observers run; carry the id only
  - `CategoryCreated(Category $category)`
  - `CategoryUpdated(Category $category)`
  - `CategoryDeleted(int $categoryId)`
  - `ProductAssignedToCategory(int $productId, int $categoryId)`
  - `ProductRemovedFromCategory(int $productId, int $categoryId)`
- Every event class:
  - extends `Marko\Core\Event\Event`
  - has a single constructor with `readonly` promoted properties (public visibility so observers can read them)
  - is not `final`

## Requirements (Test Descriptions)
- [ ] `it exposes the affected product on ProductCreated and ProductUpdated`
- [ ] `it exposes the deleted product id on ProductDeleted`
- [ ] `it exposes the affected category on CategoryCreated and CategoryUpdated`
- [ ] `it exposes the deleted category id on CategoryDeleted`
- [ ] `it exposes both product id and category id on ProductAssignedToCategory and ProductRemovedFromCategory`
- [ ] `it has every event extend Marko\Core\Event\Event so it can be passed to EventDispatcherInterface::dispatch`
- [ ] `it declares promoted constructor properties as readonly and public`

## Acceptance Criteria
- One file per event class; all under `Markommerce\Catalog\Event` namespace.
- Tests in `packages/catalog/tests/Unit/Event/` — one file per event or one consolidated `EventsShapeTest.php`, implementer's choice as long as every event is asserted.
- No event has a method other than the implicit constructor / public-properties access.
- `phpstan` clean at level 8.

## Implementation Notes
(Left blank — filled in by programmer during implementation)
