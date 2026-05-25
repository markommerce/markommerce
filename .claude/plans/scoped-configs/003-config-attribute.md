# Task 003: `#[Config]` attribute

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the `Markommerce\Config\Attributes\Config` attribute. It marks a class property as a config field, carries the stable storage `key` (decoupled from class FQN / property name), and a boolean `secret` flag for encryption-at-rest. The attribute is applied to properties already carrying `#[Scoped]` (or none, for unscoped/global-only configs).

## Context
- Pattern reference: `packages/scope/src/Attributes/Scoped.php` — same shape: `readonly class`, single constructor, `Attribute::TARGET_PROPERTY`
- The attribute carries metadata only; behavior lives in the registry/resolver
- `key` is free-form string but convention is `<package>/<group>/<name>`, e.g., `catalog/general/welcome_message`. Validation of the format is NOT part of v1 — accept any non-empty string

## Requirements (Test Descriptions)
- [x] `it constructs with a required key string`
- [x] `it defaults the secret flag to false when omitted`
- [x] `it accepts secret: true for properties needing encryption-at-rest`
- [x] `it rejects an empty key string with a clear exception at construction time`
- [x] `it is targetable to properties only (TARGET_PROPERTY)`
- [x] `it is a readonly class so the metadata cannot mutate after construction`

## Acceptance Criteria
- The attribute is reflectable via `ReflectionProperty::getAttributes(Config::class)` and yields a populated instance
- `declare(strict_types=1);` present
- No `final` keyword (project rule)
- All constructor params have explicit types

## Implementation Notes
(Left blank — filled in by programmer)
