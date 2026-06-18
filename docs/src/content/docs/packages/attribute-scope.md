---
title: markommerce/attribute-scope
description: Scoped option-label storage for attribute entities — adds per-scope label overrides to AttributeOption via a companion entity and a resolver.
---

Scope storage bridge for attribute entities. `markommerce/attribute-scope` adds per-scope label overrides to `AttributeOption` without touching the base attribute kernel. It ships a companion entity (`AttributeOptionScopedLabels`) that extends the `attribute_options` table with a `scoped_labels` JSON column, and a `ScopedOptionLabelResolver` that walks overrides from the most-specific scope to the least-specific before falling back to the base label. Scope axes are derived from the owning attribute definition's `config['axes']`, so only the axes declared on the definition are consulted during resolution.

## Installation

```bash
composer require markommerce/attribute-scope
```

The module requires `markommerce/attribute` and `markommerce/scope` (both are pulled in automatically as transitive dependencies).

## Usage

### How it wires itself in

The module's `boot` closure registers `AttributeOptionScopedLabels` as an extender of `AttributeOption` via `EntityMetadataFactory::linkExtenders()`. No manual wiring is required — install the package and the companion column appears automatically alongside `attribute_options`.

### Storing scoped label overrides

`AttributeOptionScopedLabels` implements `HasScopesInterface` and stores overrides in a `scoped_labels` JSON column. Each entry in the JSON blob is keyed by a scope signature string and maps property names to values. The only property used today is `'label'`.

```php
<?php

declare(strict_types=1);

use Markommerce\AttributeScope\Entity\AttributeOptionScopedLabels;
use Markommerce\Scope\Exceptions\ScopeStorageException;
use Markommerce\Scope\Signature\ScopeSignature;

class OptionLabelLocaliser
{
    /**
     * @throws ScopeStorageException
     */
    public function localise(AttributeOptionScopedLabels $labels): void
    {
        // Store a French override for the 'label' property
        $labels->setOverride('locale:fr', 'label', 'Couleur');

        // Store a German override
        $labels->setOverride('locale:de', 'label', 'Farbe');

        // Read back a stored override directly
        $frLabel = $labels->override('locale:fr', 'label'); // 'Couleur'

        // Check existence
        $hasFr = $labels->hasOverride('locale:fr', 'label'); // true

        // Remove an override
        $labels->clearOverride('locale:de', 'label');
    }
}
```

Writing to the `'default'` signature throws `ScopeStorageException` (the default scope is not writable as an override).

### Resolving a label for the active scope

`ScopedOptionLabelResolver::resolve()` derives the scope axes from the owning attribute definition, walks the stored overrides from the most-specific signature candidate to the least-specific (using the `scope` kernel's `ScopeWalker`), and returns the first match. If no scoped override matches the active context, it falls back to `AttributeOption::$label`.

```php
<?php

declare(strict_types=1);

use Markommerce\Attribute\Entity\AttributeOption;
use Markommerce\AttributeScope\Entity\AttributeOptionScopedLabels;
use Markommerce\AttributeScope\ScopedOptionLabelResolver;
use Markommerce\Scope\Context\ScopeContext;

class OptionLabelDisplay
{
    public function __construct(
        private ScopedOptionLabelResolver $scopedOptionLabelResolver,
    ) {}

    public function label(
        AttributeOption $option,
        AttributeOptionScopedLabels $labels,
        ScopeContext $context,
    ): string {
        // Returns the most-specific scoped override, or $option->label as fallback
        return $this->scopedOptionLabelResolver->resolve($option, $labels, $context);
    }
}
```

### Declaring scope axes on an attribute definition

The resolver reads `config['axes']` from the owning `AttributeDefinition`. Set this when creating the definition:

```php
<?php

declare(strict_types=1);

use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Attribute\Services\AttributeDefinitionService;

class ColorAttributeInstaller
{
    public function __construct(
        private AttributeDefinitionService $attributeDefinitionService,
    ) {}

    public function run(): void
    {
        $definition = new AttributeDefinition();
        $definition->code = 'color';
        $definition->entityType = 'product';
        $definition->type = 'select';
        $definition->label = 'Color';
        $definition->scopable = true;
        $definition->config = [
            'options' => ['red', 'green', 'blue'],
            'axes'    => ['locale'],
        ];

        $this->attributeDefinitionService->create($definition);
    }
}
```

Only axes listed in `config['axes']` are considered when generating scope signature candidates during resolution.

## API Reference

### `AttributeOptionScopedLabels`

Companion entity (`#[Table(extends: AttributeOption::class)]`) that stores per-scope label overrides in a `scoped_labels` JSON column. Implements `HasScopesInterface`.

| Method | Description |
|---|---|
| `setOverride(string $signature, string $property, mixed $value): void` | Store a scoped override. Throws `ScopeStorageException` if `$signature` is `'default'`. |
| `override(string $signature, string $property): mixed` | Return the stored override value, or `null` if not set. |
| `hasOverride(string $signature, string $property): bool` | Check whether an override exists for the given signature and property. |
| `clearOverride(string $signature, string $property): void` | Remove an override. Throws `ScopeStorageException` if `$signature` is `'default'`. No-op if the entry does not exist. |
| `overrides(): array<string, array<string, mixed>>` | Return all stored overrides as a nested `{signature: {property: value}}` map. |

The `$scopedLabels` property (type `?array`) is the raw JSON column value and is publicly accessible for ORM hydration, but `setOverride` / `clearOverride` are the correct write API (they keep the array sorted and null-clean).

### `ScopedOptionLabelResolver`

Resolves the display label for an `AttributeOption` under an active `ScopeContext`.

| Method | Description |
|---|---|
| `resolve(AttributeOption $option, AttributeOptionScopedLabels $labels, ScopeContext $context): string` | Walk scoped overrides from most-specific to least-specific using the axes declared in the attribute definition's `config['axes']`. Returns the first matching override's value, or `$option->label` if none match. Throws `UnknownAxisException` or `UnknownScopeException` if the context references an unknown scope. |

## Related Packages

- [markommerce/attribute](/docs/packages/attribute/) --- attribute kernel: type registry, definition service, and value validation
- [markommerce/scope](/docs/packages/scope/) --- scope kernel: `ScopeContext`, `ScopeWalker`, `HasScopesInterface`
- [markommerce/catalog-attribute-scope](/docs/packages/catalog-attribute-scope/) --- scoped product attribute values (the `Product`-side counterpart)
