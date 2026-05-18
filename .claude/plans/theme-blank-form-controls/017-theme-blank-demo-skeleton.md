# Task 017: Scaffold `theme-blank-demo` Package Skeleton

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the new `packages/theme-blank-demo/` package directory with its composer + npm manifests, an empty `module.php`, a default `config/theme_blank_demo.php` with `enabled => false`, a placeholder `README.md`, and the `ComposerManifestTest`. Also register the package in the repo-root `composer.json` under `require-dev`. This is the foundation that the rest of the extraction depends on.

## Context
- Mirror the existing `packages/frontend-demo/` skeleton exactly — only the names change.
- Related files (CREATE):
  - `packages/theme-blank-demo/composer.json`
  - `packages/theme-blank-demo/package.json`
  - `packages/theme-blank-demo/module.php`
  - `packages/theme-blank-demo/config/theme_blank_demo.php`
  - `packages/theme-blank-demo/README.md` (placeholder — full README is task 022)
  - `packages/theme-blank-demo/tests/Unit/ComposerManifestTest.php`
  - `packages/theme-blank-demo/src/.gitkeep`
  - `packages/theme-blank-demo/resources/views/.gitkeep`
  - `packages/theme-blank-demo/resources/css/.gitkeep`
- Related files (MODIFY):
  - `composer.json` (repo root) — add `"markommerce/theme-blank-demo": "self.version"` to `require-dev`, with the array kept sorted (its lines are alphabetised — see existing entries `markommerce/frontend-demo`, `pestphp/pest`, etc.).
- Reference (do not modify):
  - `packages/frontend-demo/composer.json`
  - `packages/frontend-demo/package.json`
  - `packages/frontend-demo/module.php`
  - `packages/frontend-demo/config/frontend_demo.php`
  - `packages/frontend-demo/tests/Unit/ComposerManifestTest.php`

## Manifest Shape

### `composer.json`

Mirror `packages/frontend-demo/composer.json`. Change:
- `name` → `markommerce/theme-blank-demo`
- `description` → "Theme-blank showcase module — renders the full set of `mk-*` primitives and form controls as a developer demo page."
- `autoload` PSR-4 → `Markommerce\\ThemeBlankDemo\\`
- `autoload-dev` PSR-4 → `Markommerce\\ThemeBlankDemo\\Tests\\`
- Keep `type: marko-module`, `extra.marko.module: true`
- Keep the same `require` block as frontend-demo (marko/* + markommerce/frontend + markommerce/theme-blank)
- Keep the same `require-dev` block (marko/testing, pestphp/pest ^4.0)

### `package.json`

```json
{
  "name": "@markommerce/theme-blank-demo",
  "type": "module",
  "version": "0.0.1",
  "private": true,
  "markommerce": {
    "extension": "./resources/js/index.ts",
    "priority": 1010
  },
  "dependencies": {
    "@markommerce/frontend": "*",
    "@markommerce/theme-blank": "*"
  },
  "peerDependencies": {
    "lit": "^3.0",
    "open-props": "^1.7"
  }
}
```

Note: priority 1010 (just above `frontend-demo`'s 1000) so the order is deterministic and reflects the dependency chain.

### `composer.json` `require-dev`

The new `composer.json` must include `marko/testing: "self.version"` and `pestphp/pest: "^4.0"` in `require-dev` (mirrors `packages/frontend-demo/composer.json` lines 17-20). Without `marko/testing`, the migrated `ThemeBlankDemoControllerTest` (task 021) cannot bootstrap a router and the tests added in task 019 will fail to instantiate the test helpers.

### `module.php`

```php
<?php

declare(strict_types=1);

return [];
```

### `config/theme_blank_demo.php`

```php
<?php

declare(strict_types=1);

return [
    'enabled' => false,
];
```

### `README.md` (placeholder)

Single line content: `# markommerce/theme-blank-demo` plus a one-paragraph teaser pointing at the docs site. Task 022 expands it.

### Root `composer.json`

Add the new package under `require-dev`, keeping the existing alphabetical ordering. Current `require-dev` (relevant slice):

```
"friendsofphp/php-cs-fixer": "^3.92",
"markommerce/frontend-demo": "self.version",
"pestphp/pest": "^4.3",
```

Inserted line goes after `markommerce/frontend-demo`:

```
"markommerce/theme-blank-demo": "self.version",
```

## Requirements (Test Descriptions)

Tests in `packages/theme-blank-demo/tests/Unit/ComposerManifestTest.php` (mirror the existing one in `frontend-demo`):

- [ ] `it has a composer.json declaring name markommerce/theme-blank-demo and type marko-module`
- [ ] `it requires markommerce/frontend, markommerce/theme-blank, marko/core, marko/routing, marko/view, marko/view-latte, marko/layout, marko/config all at self.version`
- [ ] `it autoloads Markommerce\\ThemeBlankDemo\\ from src/`
- [ ] `it autoloads Markommerce\\ThemeBlankDemo\\Tests\\ from tests/`
- [ ] `it sets extra.marko.module to true`
- [ ] `it provides config/theme_blank_demo.php with the enabled key defaulting to false`
- [ ] `it the repo-root composer.json registers markommerce/theme-blank-demo under require-dev at self.version`
- [ ] `it has a placeholder README.md mentioning markommerce`
- [ ] `it the package.json declares markommerce.extension pointing at ./resources/js/index.ts and markommerce.priority set to 1010`

## Acceptance Criteria
- All requirements have passing tests
- `composer validate` exits 0 on the new package manifest (covered by an existing assertion in the cloned test)
- Root `composer.json` parses with `composer validate --no-check-all`
- Stylelint/phpcs/phpstan are not affected (only new files added)

## Implementation Notes
(Left blank — filled in by programmer during implementation)
