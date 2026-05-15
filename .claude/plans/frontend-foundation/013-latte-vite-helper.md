# Task 013: Implement Latte {vite} function/extension

**Status**: completed
**Depends on**: 003
**Retry count**: 0

## Description

Build a Latte extension at `packages/frontend/src/View/Latte/ViteExtension.php` that registers a `{vite('entry.ts')}` function (and `{vite()}` for the configured default entry). The function delegates to `Marko\Vite\Vite::headTags()` and returns the rendered tags as raw HTML inside the template head. Wire it through `Markommerce\Frontend\View\Latte\ViteExtension` as a Latte extension so any template loaded via `marko/view-latte` picks it up. Tested with Pest using a fake `Vite` (or the real one against a fixture manifest).

## Context

- File: `packages/frontend/src/View/Latte/ViteExtension.php`.
- Extend Latte's abstract `Latte\Extension` class (NOT an interface — `Latte\Extension` is `abstract class`, verified against `vendor/latte/latte/src/Latte/Extension.php`). Implement `getFunctions(): array` returning `['vite' => $this->vite(...)]` (PHP 8.1+ first-class callable syntax) or a closure that calls `$this->vite($entry)`.
- The function returns `Latte\Runtime\Html` so the output is not escaped.
- The PHP class follows project standards: `declare(strict_types=1)`, constructor injection only, `readonly class`, no `final`. Constructor argument: `Marko\Vite\Vite $vite` — `Vite` is a concrete class (not an interface), so the standard parameter name is simply `vite` (the camelCase-of-classname rule applies).
- Exception type: `ViteHelperException extends MarkoException` for misconfiguration, with factory methods that include `message`, `context`, `suggestion`.
- Tests: `packages/frontend/tests/Unit/View/Latte/ViteExtensionTest.php` using Pest. Cover: function returns `Html` for known entry, falls back to configured default when no argument, raises a typed exception on missing entry.

## Requirements (Test Descriptions)

- [ ] `it registers a Latte function named vite that returns Html-typed output`
- [ ] `it delegates to Marko\\Vite\\Vite::headTags with the explicit entry argument`
- [ ] `it falls back to the configured default entry when no argument is passed`
- [ ] `it returns the Html wrapper so Latte does not escape the resulting tags`
- [ ] `it throws ViteHelperException with context and suggestion when the explicit entry is empty string`
- [ ] `it propagates ViteConfigurationException from the underlying Vite service when configuration is missing`
- [ ] `it integrates with a Latte engine fixture and renders an inline vite() call to the manifest tags`

## Acceptance Criteria

- All tests pass via `./vendor/bin/pest packages/frontend/tests/`.
- PHPStan level 8 clean on the new code.
- PHPCS clean.
- `@throws` tags present on every method that propagates exceptions.

## Implementation Notes

(Left blank — filled in by programmer during implementation)
