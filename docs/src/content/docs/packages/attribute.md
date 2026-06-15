---
title: markommerce/attribute
description: Entity-agnostic custom-attribute kernel for Markommerce stores — type registry, definition CRUD, and value validation.
---

Entity-agnostic custom-attribute kernel for Markommerce stores. `markommerce/attribute` separates attribute definitions (database rows) from attribute types (code): a pluggable type registry ships eight built-in types, and downstream modules can add or replace types without touching stored definitions. The package provides the definition service with structural guards, a reserved-code provider, and a value-validation entry point. It ships no storage driver --- install `markommerce/attribute-pgsql` for PostgreSQL persistence.

## Installation

```bash
composer require markommerce/attribute
```

A storage driver is also required:

```bash
composer require markommerce/attribute-pgsql
```

## Usage

### Attribute types

The module registers eight built-in types during boot. Each type is a singleton registered into `AttributeTypeRegistry`:

| Code | Class | `facetKind` | Notes |
|---|---|---|---|
| `text` | `TextType` | `Term` | Raw string pass-through |
| `int` | `IntType` | `Term` | PHP `int` only |
| `decimal` | `DecimalType` | `Range` | String/numeric input; optional `scale` config key |
| `bool` | `BoolType` | `Term` | PHP `bool` only |
| `date` | `DateType` | `Range` | `Y-m-d` strings or `DateTimeImmutable`; stored as `Y-m-d` string |
| `select` | `SelectType` | `Term` | Validates against `config['options']` array |
| `multiselect` | `MultiselectType` | `Term` | Array; each element validated against `config['options']` |
| `entityRef` | `EntityRefType` | `Term` | Positive integer ID; requires `config['targetEntityType']` |

`AttributeTypeRegistry` is a module singleton. The last registration for a given code wins, so downstream modules can replace any built-in type by re-registering on the same code in their own `boot` closure.

### Registering a custom type

Implement `AttributeTypeInterface` and register it from your module's `boot`:

```php title="your-package/module.php"
<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Markommerce\Attribute\Contracts\AttributeTypeInterface;
use Markommerce\Attribute\Registry\AttributeTypeRegistry;
use Markommerce\Attribute\Type\FacetKind;

return [
    'boot' => static function (ContainerInterface $container): void {
        $registry = $container->get(AttributeTypeRegistry::class);
        $registry->register(new class implements AttributeTypeInterface {
            public function code(): string { return 'html'; }
            public function cast(mixed $raw, $definition): mixed { return strip_tags((string) $raw); }
            public function serialize(mixed $value): mixed { return $value; }
            public function deserialize(mixed $stored): mixed { return $stored; }
            public function facetKind(): FacetKind { return FacetKind::None; }
        });
    },
];
```

To replace a built-in type, use the same code string as the type you want to override.

### Creating attribute definitions

Inject `AttributeDefinitionService` and call `create()`. The service enforces four guards before persisting:

1. **Unknown type** --- the `type` code must be registered in `AttributeTypeRegistry`.
2. **Reserved code** --- the `code` must not shadow a native entity column (when the entity type is mapped).
3. **Duplicate code** --- `(entityType, code)` must not already exist.
4. **Options gate** --- `$options` may only be non-empty for `select` and `multiselect` types.

```php
<?php

declare(strict_types=1);

use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Attribute\Entity\AttributeOption;
use Markommerce\Attribute\Services\AttributeDefinitionService;

class ProductAttributeInstaller
{
    public function __construct(
        private AttributeDefinitionService $attributeDefinitionService,
    ) {}

    public function run(): void
    {
        // Simple text attribute
        $definition = new AttributeDefinition();
        $definition->code = 'material';
        $definition->entityType = 'product';
        $definition->type = 'text';
        $definition->label = 'Material';
        $definition->required = false;
        $this->attributeDefinitionService->create($definition);

        // Select attribute with options
        $colorDef = new AttributeDefinition();
        $colorDef->code = 'color';
        $colorDef->entityType = 'product';
        $colorDef->type = 'select';
        $colorDef->label = 'Color';
        $colorDef->config = ['options' => ['red', 'green', 'blue']];

        $red = new AttributeOption();
        $red->value = 'red';
        $red->label = 'Red';
        $red->position = 1;

        $green = new AttributeOption();
        $green->value = 'green';
        $green->label = 'Green';
        $green->position = 2;

        $this->attributeDefinitionService->create($colorDef, [$red, $green]);
    }
}
```

### Deleting attribute definitions

`AttributeDefinitionService::delete()` removes the definition's options first, then the definition itself:

```php
<?php

declare(strict_types=1);

use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Attribute\Services\AttributeDefinitionService;

class ProductAttributeRemover
{
    public function __construct(
        private AttributeDefinitionService $attributeDefinitionService,
    ) {}

    public function remove(AttributeDefinition $definition): void
    {
        $this->attributeDefinitionService->delete($definition);
    }
}
```

### Validating values

`AttributeValueValidator` is the single entry point for validating and casting a raw value against a definition. It resolves the type from the registry, enforces `required`, then delegates to the type's `cast()` method. Pass `$allowedOptions` to validate against a runtime-provided list instead of reading from `definition->config()['options']`:

```php
<?php

declare(strict_types=1);

use Markommerce\Attribute\Contracts\AttributeDefinitionInterface;
use Markommerce\Attribute\Validation\AttributeValueValidator;

class ProductAttributeWriter
{
    public function __construct(
        private AttributeValueValidator $attributeValueValidator,
    ) {}

    public function write(AttributeDefinitionInterface $definition, mixed $rawValue): mixed
    {
        // Returns the cast value, or throws InvalidAttributeValueException
        return $this->attributeValueValidator->validate($definition, $rawValue);
    }
}
```

### Reserved codes

`ReservedCodeProvider` derives reserved attribute codes for an entity class by reading its column metadata. Both column names and PHP property names are reserved so that a custom attribute cannot shadow a native entity field:

```php
<?php

declare(strict_types=1);

use Markommerce\Attribute\Reserved\ReservedCodeProvider;

class AttributeCodeChecker
{
    public function __construct(
        private ReservedCodeProvider $reservedCodeProvider,
    ) {}

    public function isReserved(string $entityClass, string $code): bool
    {
        $reserved = $this->reservedCodeProvider->reservedCodes($entityClass);
        return in_array($code, $reserved, strict: true);
    }
}
```

`AttributeDefinitionService` calls `ReservedCodeProvider` automatically when it can resolve an entity class for the definition's entity type. Two mechanisms supply that mapping:

- **Constructor `entityTypeMap`** --- a plain `array<string, class-string>` passed directly to `AttributeDefinitionService`. Takes precedence over the registry.
- **`AttributeEntityClassMap` singleton** --- a module-level registry that other packages populate during their `boot` phase via `$entityClassMap->register('entity_type', EntityClass::class)`. `markommerce/catalog-attribute` uses this to register `product → Product::class` without needing to rebuild `AttributeDefinitionService`.

Entity types with no mapped class (in either source) skip reserved-code checking entirely.

### `AttributeBacking` and `FacetKind` enums

`AttributeBacking` declares how a custom attribute value is stored on an entity:

| Case | Meaning |
|---|---|
| `Json` | Value is stored in a JSON/JSONB blob alongside the entity |
| `Column` | Value is stored in a dedicated column on the entity (used by static attribute definitions) |

`FacetKind` declares what kind of faceted search the type supports:

| Case | Types |
|---|---|
| `Term` | Discrete equality facets (`text`, `int`, `bool`, `select`, `multiselect`, `entityRef`) |
| `Range` | Numeric or date range facets (`decimal`, `date`) |
| `None` | No faceting |

## API Reference

### `AttributeValueAccessorInterface`

Generic contract for reading and writing attribute values on an entity. Implement this interface to provide entity-specific accessor behaviour. `markommerce/catalog-attribute` ships `ProductAttributeAccessor` as the `Product` implementation.

| Method | Description |
|---|---|
| `set(object $entity, string $code, mixed $raw): void` | Validate, cast, and store a raw value on the entity. Throws `AttributeDefinitionNotFoundException` if the code is not registered, or a validation exception if the value is invalid. |
| `get(object $entity, string $code): mixed` | Retrieve the current value for a code. Returns the definition's `defaultValue` (cast) if no value has been stored. Throws `AttributeDefinitionNotFoundException`. |
| `all(object $entity): array<string, mixed>` | Return all attribute values for the entity as a `{code: value}` map. |
| `clear(object $entity, string $code): void` | Remove a stored value for a code. No-op for `Column`-backed (static) attributes. Throws `AttributeDefinitionNotFoundException`. |

### `AttributeEntityClassMap`

Singleton registered by the module. Downstream packages call `register()` from their `boot` closure to associate an entity-type string with a PHP entity class. `AttributeDefinitionService` consults this map (as a fallback to its constructor `entityTypeMap`) when checking reserved codes.

| Method | Description |
|---|---|
| `register(string $entityType, string $entityClass): void` | Associate an entity type with a class. Re-registering the same pair is a no-op. Registering a different class for an already-mapped type throws `DuplicateEntityClassRegistrationException`. |
| `all(): array<string, class-string>` | Return the full map of registered entity types to classes. |

### `AttributeTypeInterface`

| Method | Description |
|---|---|
| `code(): string` | Unique type identifier (e.g. `'text'`, `'select'`). |
| `cast(mixed $raw, AttributeDefinitionInterface $definition): mixed` | Validate and normalise a raw value. Throws `InvalidAttributeValueException` on failure. |
| `serialize(mixed $value): mixed` | Prepare a cast value for storage. |
| `deserialize(mixed $stored): mixed` | Restore a stored value to its runtime form. |
| `facetKind(): FacetKind` | Declare the faceting behaviour for this type. |

### `AttributeDefinitionInterface`

| Method | Description |
|---|---|
| `code(): string` | Machine-readable attribute code (unique within an entity type). |
| `entityType(): string` | The entity type this attribute belongs to (e.g. `'product'`). |
| `type(): string` | Type code; must be registered in `AttributeTypeRegistry`. |
| `backing(): AttributeBacking` | Storage backing (`Json` or `Column`). |
| `isRequired(): bool` | Whether a value is mandatory. |
| `config(): array<string, mixed>` | Type-specific configuration (e.g. `['options' => [...]]` for select types, `['scale' => 2]` for decimal, `['targetEntityType' => '...']` for entityRef). |

### `AttributeDefinitionRepositoryInterface`

Extends Marko's `RepositoryInterface<AttributeDefinition>`.

| Method | Description |
|---|---|
| `findByCode(string $entityType, string $code): ?AttributeDefinition` | Look up a definition by entity type and code. Returns `null` if not found. |
| `optionsFor(AttributeDefinition $definition): list<AttributeOption>` | Return all options for a definition. |
| `saveOption(AttributeOption $option): void` | Persist a single option. |
| `deleteOptionsFor(AttributeDefinition $definition): void` | Remove all options for a definition. |

### `AttributeTypeRegistry`

Singleton registered by the module.

| Method | Description |
|---|---|
| `register(AttributeTypeInterface $type): void` | Add or replace a type. Last registration wins. |
| `get(string $code): AttributeTypeInterface` | Retrieve a type by code. Throws `UnknownAttributeTypeException` if not found. |
| `has(string $code): bool` | Check whether a type is registered. |
| `all(): array<string, AttributeTypeInterface>` | Return all registered types keyed by code. |

### `AttributeDefinitionService`

| Method | Description |
|---|---|
| `create(AttributeDefinition $definition, list<AttributeOption> $options = []): void` | Validate and persist a new definition (with optional options for select types). |
| `delete(AttributeDefinition $definition): void` | Delete a definition and cascade-remove its options. |

### `AttributeValueValidator`

| Method | Description |
|---|---|
| `validate(AttributeDefinitionInterface $definition, mixed $raw, list<string> $allowedOptions = []): mixed` | Validate and cast a raw value. Returns the cast value, or `null` if `$raw` is `null` and the attribute is not required. Throws `InvalidAttributeValueException` or `UnknownAttributeTypeException`. |

### `AttributeDefinition` entity

Mapped to the `attribute_definitions` table.

| Property | Column | Type | Description |
|---|---|---|---|
| `$id` | `id` | `int\|null` | Auto-increment primary key. |
| `$code` | `code` | `string` (64) | Machine-readable code; unique per entity type. |
| `$entityType` | `entity_type` | `string` (64) | Entity type identifier. |
| `$type` | `type` | `string` (64) | Attribute type code. |
| `$label` | `label` | `string` (255) | Human-readable label. |
| `$required` | `required` | `bool` | Whether the value is mandatory. |
| `$defaultValue` | `default_value` | `string\|null` | Default value stored as text. |
| `$backing` | `backing` | `string` (16) | `'Json'` or `'Column'`. |
| `$filterable` | `filterable` | `bool` | Whether the attribute can be used in filters. |
| `$searchable` | `searchable` | `bool` | Whether the attribute is included in search. |
| `$facetable` | `facetable` | `bool` | Whether the attribute generates facets. |
| `$scopable` | `scopable` | `bool` | Whether the attribute supports scoped values (Phase 2). |
| `$config` | `config` | `array\|null` | JSONB type-specific config. |

### `AttributeOption` entity

Mapped to the `attribute_options` table.

| Property | Column | Type | Description |
|---|---|---|---|
| `$id` | `id` | `int\|null` | Auto-increment primary key. |
| `$attributeId` | `attribute_id` | `int\|null` | FK to `attribute_definitions`. |
| `$value` | `value` | `string` (255) | Option value used in validation. |
| `$label` | `label` | `string` (255) | Human-readable label. |
| `$position` | `position` | `int` | Sort order. |

### Exceptions

| Exception | When thrown |
|---|---|
| `UnknownAttributeTypeException` | A type code is not registered in `AttributeTypeRegistry`. |
| `ReservedAttributeCodeException` | A definition code conflicts with a native entity column name or property. |
| `DuplicateAttributeCodeException` | A definition with the same `(entityType, code)` pair already exists. |
| `OptionsNotAllowedException` | Options were passed to `create()` for a type that is not `select` or `multiselect`. |
| `InvalidAttributeValueException` | A raw value fails type-level validation or casting. |
| `InvalidAttributeOptionException` | A `select`/`multiselect` value is not in the allowed options list. |
| `AttributeDefinitionNotFoundException` | A definition lookup by code returns no result (thrown by callers that treat a missing definition as fatal). |
| `DuplicateEntityClassRegistrationException` | `AttributeEntityClassMap::register()` was called with a different class for an already-mapped entity type. |

## Related Packages

- [markommerce/attribute-pgsql](/docs/packages/attribute-pgsql/) --- PostgreSQL storage driver
- [markommerce/catalog-attribute](/docs/packages/catalog-attribute/) --- binds the attribute kernel to `Product`; ships `ProductAttributeAccessor`
