# Package Standard

> **Entry point for contributors.** This document is the single source of truth for markommerce package structure. Read it alongside [`CLAUDE.md`](../CLAUDE.md) (project-wide rules) and [`architecture.md`](architecture.md) (module system, naming, interface/driver split).

---

## Package Types

Markommerce has two package types, distinguished by the `"type"` field in `composer.json`.

| Type | `composer.json` `"type"` | Purpose |
|---|---|---|
| **marko-module** | `"marko-module"` | Ships Marko bindings, plugins, observers, or registrations. Discovered and loaded by the Marko framework automatically. |
| **library** | `"library"` | Pure PHP library — contracts, value objects, utilities. No Marko framework coupling. |

---

## Required Files per Package Type

### marko-module Package

Every `marko-module` package MUST contain:

```
<package-root>/
  composer.json        # Package manifest — type must be "marko-module"
  README.md            # Usage documentation
  LICENSE              # MIT text (canonical text below)
  .gitattributes       # Export-ignore rules (canonical content below)
  src/                 # PHP source code
  tests/               # Test suite
  tests/Pest.php       # Pest bootstrap (canonical skeleton below)
```

`module.php` is **conditional** — see [Module Registration](#module-registration-modulephp) below.

### library Package

Every `library` package MUST contain:

```
<package-root>/
  composer.json        # Package manifest — type must be "library"
  README.md            # Usage documentation
  LICENSE              # MIT text (canonical text below)
  .gitattributes       # Export-ignore rules (canonical content below)
  src/                 # PHP source code
```

`tests/` and `module.php` are **not required** for library packages. A library that does have tests MUST include `tests/Pest.php`.

---

## Module Registration (`module.php`)

A `module.php` file MUST exist only when the package has at least one of:

- Interface bindings (`'bindings' => [...]`)
- Plugin declarations
- Observer declarations
- Any other Marko registration

An empty `return [];` is **forbidden** — it adds noise without value. If a package currently has no registrations, omit `module.php` entirely. Add it when the first registration is needed.

Rationale: the Marko framework in the upstream codebase ships `module.php` in 68 of 80 packages — only those that actually register something.

Minimal valid `module.php`:

```php
<?php

declare(strict_types=1);

return [
    'bindings' => [
        PaymentGatewayInterface::class => StripePaymentGateway::class,
    ],
];
```

---

## Canonical File Contents

### LICENSE

Every package ships the following MIT text verbatim. The copyright holder is `Devtomic LLC`. The `FilePresenceTest` diffs package LICENSE files against this block.

```
MIT License

Copyright (c) Devtomic LLC

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

### .gitattributes

Every package ships the following `.gitattributes` content verbatim. The `FilePresenceTest` diffs package `.gitattributes` files against this block.

Note: `/phpunit.xml.dist` is listed for forward-compatibility even though no package currently ships that file. Do not remove it.

```
/tests              export-ignore
/.github             export-ignore
/.gitattributes     export-ignore
/.gitignore         export-ignore
/phpunit.xml.dist   export-ignore
```

### tests/Pest.php

Every package that ships a `tests/` directory MUST include the following `Pest.php` skeleton verbatim. The `FilePresenceTest` diffs against this block.

```php
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/
```

---

## `composer.json` Conventions

### PSR-4 Autoload Roots

A package MUST declare **exactly one PSR-4 autoload root** mapped to `src/`:

```json
"autoload": {
    "psr-4": {
        "Markommerce\\Catalog\\": "src/"
    }
}
```

**Single documented exception:** packages that ship `marko/database` seeders may declare a second PSR-4 root mapped to `Seed/`. See [Seed/ Directory Convention](#seed-directory-convention) below.

```json
"autoload": {
    "psr-4": {
        "Markommerce\\Catalog\\": "src/",
        "Markommerce\\Catalog\\Seed\\": "Seed/"
    }
}
```

### PSR-4 Autoload-Dev Root

Every package with a `tests/` directory MUST declare **exactly one PSR-4 autoload-dev root** mapped to `tests/`:

```json
"autoload-dev": {
    "psr-4": {
        "Markommerce\\Catalog\\Tests\\": "tests/"
    }
}
```

### Pest Allow-Plugins Block

Every package that uses Pest MUST include the following `config` block in `composer.json`. Without it, Composer will prompt interactively during CI installs.

```json
"config": {
    "allow-plugins": {
        "pestphp/pest-plugin": true
    }
}
```

### Pest in require-dev

Every `marko-module` package with a `tests/` directory MUST declare both `marko/testing` and `pestphp/pest` in `require-dev`:

```json
"require-dev": {
    "marko/testing": "self.version",
    "pestphp/pest": "^4.0"
}
```

Rationale: `marko/testing` provides the fake implementations, in-memory repositories, and test application bootstrap that integration tests depend on. Omitting it forces tests to roll their own bootstrapping, which diverges from the framework conventions.

---

## `Seed/` Directory Convention

### Why `Seed/` is at the package root, not inside `src/`

The Marko database layer discovers seeder classes by globbing `vendor/*/*/Seed` at a fixed depth (two path segments below the vendor root). The discovery logic lives in:

```
marko/database/src/Seed/SeederDiscovery.php
```

Relevant excerpt from `SeederDiscovery::discoverInVendor()`:

```php
foreach (glob($vendorPath . '/*/*/Seed', GLOB_ONLYDIR) as $seedDir) {
    $seeders = array_merge($seeders, $this->discoverInPath($seedDir));
}
```

This glob resolves to `vendor/markommerce/catalog/Seed` — the `Seed/` directory MUST be a **direct child of the package root**, not nested under `src/`. Placing seeders in `src/Seed/` would resolve to `vendor/markommerce/catalog/src/Seed`, which `SeederDiscovery` never visits.

### Required layout

```
<package-root>/
  Seed/
    CatalogSeeder.php    # Seeder class with #[Seeder] attribute
  src/
    ...
```

### composer.json declaration

```json
"autoload": {
    "psr-4": {
        "Markommerce\\Catalog\\": "src/",
        "Markommerce\\Catalog\\Seed\\": "Seed/"
    }
}
```

---

## Exceptions Directory: `src/Exceptions/` (Plural)

Exception classes MUST live in `src/Exceptions/` (plural). The singular form `src/Exception/` is **forbidden**.

Rationale: consistency with the directory-per-noun-plural convention used throughout the codebase (`Repositories/`, `Services/`, `Entities/`). Singular `Exception/` was an early inconsistency and must not be replicated.

```
src/
  Exceptions/
    ProductNotFoundException.php
    CategoryNotFoundException.php
```

All exception classes extend `MarkoException` with named parameters `message`, `context`, `suggestion`. See [`code-standards.md`](code-standards.md) for the full exception pattern.

---

## `resources/` Sub-Conventions

Packages that ship front-end assets use a `resources/` directory at the package root.

### Allowed top-level children

The only allowed direct children of `resources/` are:

| Directory | Purpose |
|---|---|
| `resources/css/` | Compiled and source CSS stylesheets |
| `resources/js/` | TypeScript / JavaScript source files |
| `resources/views/` | Latte template files |

No other top-level directories are allowed inside `resources/`.

### `.gitkeep` policy

`.gitkeep` is allowed **only** in directories that would otherwise be empty (i.e., the directory has no tracked files yet). Once a real file is added, remove `.gitkeep`.

### CSS exports

Packages that ship CSS under `resources/css/` MUST expose those files via the `package.json` `"exports"` map so consumers can import them by path:

```json
{
    "exports": {
        ".": "./resources/js/index.ts",
        "./css/layers.css": "./resources/css/layers.css",
        "./css/*": "./resources/css/*"
    }
}
```

Rationale: without an explicit exports map, bundlers cannot resolve subpath imports reliably across different Node resolution modes.

---

## `.generated/` Subdirectories

Build tools write generated files into `resources/js/.generated/` at runtime. The root `.gitignore` already excludes all `.generated/` directories via:

```
packages/*/resources/js/.generated/
```

Consequently, **per-package `.gitignore` files inside `.generated/` directories are forbidden**. They are redundant and create confusion about which ignore rules apply. The root `.gitignore` is the single authority.

---

## The Two Meanings of "Layout"

The word "layout" appears in two distinct contexts in markommerce. Understanding the difference is essential.

### 1. Markommerce Layout DSL — `<package-root>/layout/`

The `markommerce/layout` package defines a PHP DSL for declaring page composition: which components fill which slots, what data providers bind to which tokens, and how templates extend each other. These declarations live in a `layout/` directory at the package root and contain PHP files that `return new Layout(...)`.

Example (`packages/catalog/layout/category_show.php`):

```php
return new Layout(
    handle: [CategoryController::class, 'show'],
    extends: OneColumnLayout::class,
    slots: [...],
);
```

**Location:** `<package-root>/layout/*.php`
**Purpose:** Declares component composition and data wiring for a specific route handle.

### 2. Latte Template Inheritance — `<package-root>/resources/views/layout/`

Latte (the templating engine) uses the keyword "layout" for template inheritance — a base template that child templates extend with `{extends}`. These are `.latte` files placed under `resources/views/layout/` and define the HTML skeleton (columns, header/footer regions, block definitions).

Example (`packages/theme-blank/resources/views/layout/base.latte`):

```latte
<!doctype html>
<html lang="en">
<head>...</head>
<body>
{block body}
    <header>{block header}{/block}</header>
    <main>{block main}{/block}</main>
    <footer>{block footer}{/block}</footer>
{/block}
</body>
</html>
```

**Location:** `<package-root>/resources/views/layout/*.latte`
**Purpose:** HTML structural skeleton for Latte template inheritance.

### Summary

| | Path | File type | Purpose |
|---|---|---|---|
| Layout DSL | `<pkg>/layout/*.php` | PHP | Component composition + data wiring |
| Latte inheritance | `<pkg>/resources/views/layout/*.latte` | Latte | HTML skeleton + block definitions |

Both names are intentional and coexist. Do not conflate them.

---

## No Per-Package CHANGELOG.md

Markommerce does **not** maintain per-package `CHANGELOG.md` files. This matches the marko upstream convention: the upstream monorepo ships 80 packages and none carries a `CHANGELOG.md`.

Rationale: in a unified-versioning monorepo every package changes together. A single repository-level changelog (or release notes on GitHub) is more accurate and less labour-intensive than 20+ per-package files that are inevitably out of date.

If a contributor opens a PR that adds a `CHANGELOG.md` to a package, request its removal.

---

## Quick Reference Checklist

Use this checklist when creating a new package or auditing an existing one.

**All packages:**
- [ ] `composer.json` present with correct `"type"` field
- [ ] `README.md` present
- [ ] `LICENSE` present and matches canonical MIT text exactly
- [ ] `.gitattributes` present and matches canonical content exactly
- [ ] `src/` present
- [ ] Exactly one PSR-4 autoload root pointing to `src/`
- [ ] Exception classes (if any) live in `src/Exceptions/` (plural)
- [ ] No `CHANGELOG.md`

**marko-module packages additionally:**
- [ ] `tests/` present
- [ ] `tests/Pest.php` present and matches canonical skeleton exactly
- [ ] Exactly one PSR-4 autoload-dev root pointing to `tests/`
- [ ] `require-dev` includes `marko/testing` and `pestphp/pest`
- [ ] `config.allow-plugins.pestphp/pest-plugin: true` in `composer.json`
- [ ] `module.php` present only if the package has actual registrations
- [ ] `"extra": { "marko": { "module": true } }` in `composer.json`

**Packages with seeders additionally:**
- [ ] Seeder classes live in `<package-root>/Seed/`, NOT in `src/Seed/`
- [ ] Second PSR-4 autoload root maps the `Seed\` namespace to `Seed/`

**Packages with front-end assets additionally:**
- [ ] Only `css/`, `js/`, `views/` exist as direct children of `resources/`
- [ ] `.gitkeep` used only in empty directories
- [ ] No per-package `.gitignore` inside `.generated/` directories
- [ ] CSS files exposed via `package.json` `"exports"` map
