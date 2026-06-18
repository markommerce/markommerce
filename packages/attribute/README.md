# markommerce/attribute

Entity-agnostic custom-attribute kernel for Markommerce stores. Attribute definitions are merchant data (database rows); attribute types are code (a registry). The two are kept separate so new types can be added without touching stored definitions.

## Installation

```bash
composer require markommerce/attribute
```

## Quick Example

```php
use Markommerce\Attribute\Registry\AttributeTypeRegistry;
use Markommerce\Attribute\Type\TextType;

// Register a custom type (marko boot phase or module.php)
$registry = new AttributeTypeRegistry();
$registry->register(new TextType());

// Resolve a type by its code
$type = $registry->get('text');

// Cast a raw value against a definition
$cast = $type->cast($rawValue, $definition);
```

### Built-in types

| Code | Class | `backing` default |
|---|---|---|
| `text` | `TextType` | `Json` |
| `int` | `IntType` | `Json` |
| `decimal` | `DecimalType` | `Json` |
| `bool` | `BoolType` | `Json` |
| `date` | `DateType` | `Json` |
| `select` | `SelectType` | `Json` |
| `multiselect` | `MultiselectType` | `Json` |
| `entity_ref` | `EntityRefType` | `Json` |

Select and multiselect types read allowed values from `$definition->config()['options']`.

### Key contracts

- `AttributeTypeInterface` — `code()`, `cast()`, `serialize()`, `deserialize()`, `facetKind()`
- `AttributeDefinitionInterface` — `code()`, `entityType()`, `type()`, `backing()`, `isRequired()`, `config()`
- `AttributeTypeRegistry` — `register()`, `get()`, `has()`, `all()`; last registration wins (Preference-overridable)

### Validation

`AttributeValueValidator` is the single value-validation entry point: resolves the type, enforces `required`, validates select-option membership, and returns the cast value.

### Reserved codes

`ReservedCodeProvider` derives reserved attribute codes from an entity's declared columns via Marko `EntityMetadataFactory`, preventing shadowing of native columns.

## Documentation

Full usage, API reference, and examples: [markommerce/attribute](https://markommerce.dev/docs/packages/attribute/)
