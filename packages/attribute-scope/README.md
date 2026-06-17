# markommerce/attribute-scope

Scoped option-label storage for attribute entities --- adds per-scope label overrides to `AttributeOption` via a companion entity and a resolver that returns the most-specific match.

## Installation

```bash
composer require markommerce/attribute-scope
```

## Quick Example

The package auto-wires itself at boot. `AttributeOptionScopedLabels` is a companion that extends `attribute_options` via single-table inheritance and stores scoped label overrides in a `scoped_labels` JSON column. `ScopedOptionLabelResolver` walks those overrides from most-specific scope to least-specific, falling back to the base `AttributeOption::$label`:

```php
use Markommerce\AttributeScope\Entity\AttributeOptionScopedLabels;
use Markommerce\AttributeScope\ScopedOptionLabelResolver;
use Markommerce\Scope\Context\ScopeContext;

// Attach per-scope labels to an option
$scopedLabels = new AttributeOptionScopedLabels();
$scopedLabels->setOverride('locale:fr', 'label', 'Couleur');

// Resolve the label for the active scope context
$label = $scopedLabelResolver->resolve($option, $scopedLabels, $scopeContext);
// Returns 'Couleur' when active locale is 'fr', or $option->label otherwise.
```

Axes consulted during resolution are derived from the owning attribute definition's `config['axes']`, so only axes declared on the attribute are considered.

## Documentation

Full usage, API reference, and examples: [markommerce/attribute-scope](https://markommerce.dev/docs/packages/attribute-scope/)
