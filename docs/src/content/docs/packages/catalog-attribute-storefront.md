---
title: markommerce/catalog-attribute-storefront
description: Layered navigation for the Markommerce storefront — attribute filter contributor and facet assembler that bridges catalog attribute definitions with the category-page product grid.
---

Layered navigation for the Markommerce storefront. `markommerce/catalog-attribute-storefront` provides `AttributeProductListFilter`, `LayeredNavigationAssembler`, and a facet sidebar Latte template that bridge catalog attribute definitions with the category-page product grid. Installing this package enables attribute-based filtering and disjunctive facet sidebar rendering on category pages with no application-level wiring required.

## Installation

```bash
composer require markommerce/catalog-attribute-storefront
```

`markommerce/catalog-storefront`, `markommerce/catalog-attribute-index`, `markommerce/catalog-attribute-scope`, and their transitive dependencies are installed automatically. The module registers its bindings and boots the filter contributor via `module.php`.

## Usage

### Query-Parameter Convention

Filters use the bracketed `filter` array convention:

```
GET /catalog/category/5?filter[color][]=red&filter[color][]=blue&filter[size][]=L
```

PHP natively expands this into `['color' => ['red', 'blue'], 'size' => ['L']]`. Multiple values for the same attribute are OR-ed; multiple attribute codes are AND-ed. Unknown codes are silently dropped.

### Parsing Filter Parameters

Inject `FilterParamParser` to parse query parameters from the HTTP request into a `FilterSelection`:

```php
<?php

declare(strict_types=1);

use Markommerce\CatalogAttributeStorefront\LayeredNavigation\FilterParamParser;

class MyCategoryController
{
    public function __construct(
        private FilterParamParser $filterParamParser,
    ) {}

    public function show(Request $request): Response
    {
        // Returns a FilterSelection — unknown codes are silently dropped
        $selection = $this->filterParamParser->parse($request);
        // ...
    }
}
```

### Assembling Layered Navigation

`LayeredNavigationAssembler` returns a filtered product page, disjunctive facet counts, and active filter chips:

```php
<?php

declare(strict_types=1);

use Markommerce\CatalogAttributeStorefront\LayeredNavigation\LayeredNavigationAssembler;
use Markommerce\Catalog\Filtering\FilterSelection;

$navData = $layeredNavigationAssembler->forCategory(
    categoryId: $category->id,
    options: $resolvedPaginationOptions,
    selection: $selection,
);

// $navData->page           — Page<Product> filtered by the active selection
// $navData->facets         — list<LabeledFacet> with scope-resolved option labels
// $navData->activeFilters  — list<ActiveFilter> for the active filter chips
```

### Building Toggle URLs

`FacetToggleUrlBuilder` generates URLs that add or remove a single filter value while preserving all other parameters and resetting the page:

```php
<?php

declare(strict_types=1);

use Markommerce\CatalogAttributeStorefront\LayeredNavigation\FacetToggleUrlBuilder;

$url = $facetToggleUrlBuilder->toggle(
    baseUrl: '/catalog/category/5',
    currentParams: $request->query(),
    code: 'color',
    value: 'red',
);
// Adds ?filter[color][]=red if not active, removes it if already active.
// Page param is always reset.
```

### Facet Sidebar Template

The Latte component template at `resources/views/components/facet-sidebar.latte` renders active filter badges and per-attribute facet groups. Each facet value shows its display label, product count, and a toggle link. The template consumes `$facets` and `$activeFilters` from `ProductGridData` as populated by `ProductGridComponent`.

## Disjunctive Faceting

Facet counts are computed **disjunctively**: for each attribute, all other active filters are applied but the attribute's own filter is ignored. This ensures all values for an attribute remain visible and countable even when that attribute is already filtered — matching standard e-commerce behavior.

Example: selecting "Red" for color still shows "Blue (4)" and "Green (2)" for the color facet.

The disjunctive logic is handled by `AttributeFacetQuery` from [markommerce/catalog-attribute-index](/docs/packages/catalog-attribute-index/).

## Scope Awareness

Both `AttributeProductListFilter` and `LayeredNavigationAssembler` resolve scope signatures from the active `ScopeContext` using `SignatureCandidateEnumerator`. The most-specific candidate signature with rows in the index wins; the base (`''`) signature is the fallback. Filter and facet counts therefore reflect the correct scoped attribute values for the current locale or market.

## Integration with `catalog-storefront`

`catalog-storefront` defines `LayeredNavigationAssemblerInterface` and binds it to `NullLayeredNavigationAssembler` by default. Installing this package rebinds the interface to the real `LayeredNavigationAssembler`. `ProductGridData` carries `$facets` and `$activeFilters` fields (typed `list<object>` so `catalog-storefront` does not hard-require this package); they are empty arrays when this package is absent and populated `LabeledFacet`/`ActiveFilter` instances when it is installed.

## Module Bindings

| Binding | Notes |
|---|---|
| `LayeredNavigationAssemblerInterface` → `LayeredNavigationAssembler` | Overrides the null-object from `catalog-storefront` |
| `AttributeProductListFilter` | Registered into `ProductListFilterRegistry` at boot |
| `AttributeExistsClause` | Shared SQL builder; used by both filter and facet query |

## API Reference

### `AttributeProductListFilter`

Implements `ProductListFilterInterface`.

| Method | Return type | Description |
|---|---|---|
| `apply(RepositoryQueryBuilder $repositoryQueryBuilder, FilterSelection $filterSelection)` | `void` | Add correlated EXISTS constraints over `catalog_product_attribute_index` for each active filter key. No-op when selection is empty. |

### `LayeredNavigationAssembler`

Implements `LayeredNavigationAssemblerInterface`.

| Method | Return type | Throws | Description |
|---|---|---|---|
| `forCategory(int $categoryId, ResolvedPaginationOptions $options, FilterSelection $selection)` | `LayeredNavigationData` | `CategoryNotFoundException` | Assemble filtered product page + disjunctive facets + active filters for a category. |

### `FilterParamParser`

| Method | Return type | Description |
|---|---|---|
| `parse(Request $request)` | `FilterSelection` | Parse `filter[...]` query params. Unknown attribute codes are silently dropped. |

### `FacetToggleUrlBuilder`

| Method | Return type | Description |
|---|---|---|
| `toggle(string $baseUrl, array $currentParams, string $code, string $value)` | `string` | Toggle the given attribute value in or out of the active filter selection. Resets the `page` parameter. |

### Value Objects

| Class | Fields | Description |
|---|---|---|
| `LabeledFacet` | `code`, `type`, `list<LabeledFacetValue> values` | Facet group with scope-resolved display labels |
| `LabeledFacetValue` | `value`, `label`, `count`, `selected` | Single facet option with resolved label, product count, and selection state |
| `ActiveFilter` | `code`, `type`, `list<string> labels`, `list<string> values` | Active filter chip with resolved labels |
| `LayeredNavigationResult` | `page`, `facets`, `activeFilters` | Thin result wrapper for direct use without `ProductGridComponent` |

## PHP Version and Code Conventions

Requires **PHP 8.5+**. All source files declare `strict_types=1`. No `final` classes — open for extension via Marko Preferences. Constructor injection only.

## Related Packages

- [markommerce/catalog-attribute-index](/docs/packages/catalog-attribute-index/) --- `AttributeFacetQuery`, `AttributeExistsClause`, and the `catalog_product_attribute_index` table this package queries for filtering and faceting
- [markommerce/catalog-storefront](/docs/packages/catalog-storefront/) --- defines `LayeredNavigationAssemblerInterface` and `ProductGridData`; this package provides the real assembler implementation
- [markommerce/catalog](/docs/packages/catalog/) --- `ProductListFilterInterface`, `ProductListFilterRegistry`, `FilterSelection`; `CategoryAssignmentService::paginatedProductsInCategory()` applies registered filters
- [markommerce/catalog-attribute-scope](/docs/packages/catalog-attribute-scope/) --- `ScopedOptionLabelResolver` used to resolve scoped option labels for facet values
- [markommerce/scope](/docs/packages/scope/) --- `ScopeContext` and `SignatureCandidateEnumerator` used for scope-aware filter and facet resolution
