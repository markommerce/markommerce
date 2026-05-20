# Changelog

All notable changes to `markommerce/scope` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- `ScopeSignature` value object --- use `ScopeSignature::fromArray(['axis' => 'path'])` or `new ScopeSignature(['axis' => 'path'])` to create scope identifiers. Multi-axis composites are supported: `ScopeSignature::fromArray(['channel' => 'b2b', 'locale' => 'es'])`.
- `SignatureCandidateEnumerator` --- enumerates all candidate signatures for multi-axis resolution in descending-score order.
- `ScopeSignatureValidator` --- validates a `ScopeSignature` against the axes declared on a `#[Scoped]` attribute.
- `InvalidSignatureException` --- thrown when a `ScopeSignature` is constructed with invalid input (empty array, empty axis name, empty value, duplicate axis, malformed string).
- `InvalidSignatureForAttributeException` --- thrown when a `ScopeSignature` axes do not match the axes declared on the target property's `#[Scoped]` attribute.
- `MultiAxisWalkAtNotSupportedException` --- thrown when `ScopeWalker::walkAt()` is called with a multi-axis `ScopeSignature`. Use `walk()` with a `ScopeContext` for multi-axis resolution.
- `ScopedFieldRendererInterface` --- interface implemented by driver packages to emit DB-specific `COALESCE` expressions for scoped field ordering. Replaces the removed `ScopeSortRendererInterface`.
- `ScopedFieldExpression` --- value object carrying property, column, and ordered candidate signatures for driver rendering. Replaces `ScopeSortExpression`.

### Changed

- **BREAKING**: `Markommerce\Scope\Scope` class removed. Use `Markommerce\Scope\Signature\ScopeSignature::fromArray([...])` instead. Old single-axis `Scope` objects are not forward-compatible.
- **BREAKING**: `ScopeResolver::setOverride`, `clearOverride`, and `resolvedAt` now take `ScopeSignature` instead of the removed `Scope` class.
- **BREAKING**: `ScopeWalker::walkAt` takes a `ScopeSignature` (single-axis only --- passing a multi-axis signature throws `MultiAxisWalkAtNotSupportedException`).
- **BREAKING**: `HasScopesInterface` parameter renamed `$scopeKey` to `$signature` across all storage methods.
- **BREAKING**: `ScopeSortRendererInterface` removed; replaced by `ScopedFieldRendererInterface`. Driver packages must implement the new interface.
- **BREAKING**: `ScopeSortExpression` removed; replaced by `ScopedFieldExpression`.
- **BREAKING / DATA**: The resolution algorithm has changed from single-axis "first axis wins" to multi-axis composite overrides scored lexicographically. Stored override JSONB keys for single-axis overrides are unchanged, but resolution semantics have changed. For pre-release projects: dropping the `scopes` column and re-writing all overrides is recommended to ensure consistent behaviour.
- Single-axis `#[Scoped(axes: ['locale'])]` continues to work as before. The change is additive for single-axis usage --- existing overrides are resolved correctly. Only projects that relied on the old "first axis wins" multi-axis behaviour need to re-write their overrides.
