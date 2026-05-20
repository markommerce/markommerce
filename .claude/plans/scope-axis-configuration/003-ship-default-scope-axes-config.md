# Task 003: Ship Default locale, market, and channel Axes as packages/scope/config/scope.php

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Adds a `config/scope.php` file inside `packages/scope` that ships the three framework-default axes — `locale` (default scope `default`), `market` (default scope `default`), and `channel` (default scope `web`). Marko's `ConfigDiscovery` auto-loads each module's `config/<name>.php`, so this file becomes the base layer that packages and merchants can deep-merge on top of.

## Context

The `scope` package has no `config/` directory today. Per Marko convention, the filename `scope.php` namespaces the returned array under the `scope` config key, so `ConfigRepositoryInterface::getArray('scope.axes')` (called by `PhpScopeRegistry`) resolves to the `axes` key inside the returned array.

Schema (as established by task 002):
```php
return [
    'axes' => [
        'locale'  => ['default' => 'default', 'scopes' => ['default' => []]],
        'market'  => ['default' => 'default', 'scopes' => ['default' => []]],
        'channel' => ['default' => 'web',     'scopes' => ['web' => []]],
    ],
];
```

The test for this task `require`s the file directly (no Marko bootstrap needed) and feeds the array into a freshly-constructed `PhpScopeRegistry` via the same `makeConfigStub` helper used in `PhpScopeRegistryTest`. This proves the file's shape is accepted by the registry without depending on `ConfigDiscovery` wiring.

The test file lives at `packages/scope/tests/Unit/Config/DefaultAxesConfigTest.php`. Load the shipped config with a relative-to-test path that has no working-directory assumption:

```php
$config = require dirname(__DIR__, 3) . '/config/scope.php';
```

`dirname(__DIR__, 3)` from `tests/Unit/Config/` resolves to `packages/scope/` regardless of cwd, then `/config/scope.php` is the shipped file.

Auto-discovery via Marko's `ConfigDiscovery` (`marko/packages/config/src/ConfigDiscovery.php`) globs `<modulePath>/config/*.php` and namespaces each file by its basename — so once `packages/scope/config/scope.php` exists, `ConfigRepositoryInterface::getArray('scope.axes')` resolves to the `axes` key automatically in any host application that registers the scope module. The unit test bypasses this wiring and feeds the raw array; the end-to-end feature test in task 006 also bypasses `ConfigDiscovery` and uses the same `require` strategy.

- Files to create:
  - `packages/scope/config/scope.php`
  - `packages/scope/tests/Unit/Config/DefaultAxesConfigTest.php` (new directory)
- Patterns to follow:
  - `<?php` + `declare(strict_types=1);` at the top of the config file (CLAUDE.md key rule #1).
  - No comments inside the config array (well-named keys self-document — CLAUDE.md "Default to writing no comments").
  - Re-use `makeConfigStub()` from `PhpScopeRegistryTest` (extract or duplicate — the test file is small) to feed the config to the registry.

## Requirements (Test Descriptions)
- [ ] `it declares locale, market, and channel axes`
- [ ] `it gives the locale axis a single scope named default set as its default`
- [ ] `it gives the market axis a single scope named default set as its default`
- [ ] `it gives the channel axis a single scope named web set as its default`
- [ ] `it is accepted by PhpScopeRegistry without error`

## Acceptance Criteria
- All requirements have passing tests.
- `packages/scope/config/scope.php` returns a `declare(strict_types=1);` PHP array shaped as above.
- The file has no `<?php echo`, no side effects, and contains no logic — pure data.
- `composer test` for the `scope` package is green. **Do not gate on `composer test:all`** — `scope-pgsql`'s integration test stays red until task 007 lands.
- Code follows code standards.

## Implementation Notes
(Left blank - filled in by programmer during implementation)
