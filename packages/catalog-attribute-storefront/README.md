# markommerce/catalog-attribute-storefront

Layered navigation for the Markommerce storefront — attribute filter contributor and facet assembler that bridges catalog attribute definitions with the category-page product grid. Install this package to enable facet sidebar rendering and attribute-based product filtering on category pages.

## Installation

```bash
composer require markommerce/catalog-attribute-storefront
```

`markommerce/catalog-storefront`, `markommerce/catalog-attribute-index`, `markommerce/catalog-attribute-scope`, and their transitive dependencies are installed automatically. The module registers its bindings and boots the filter contributor via `module.php` — no manual wiring required.

## Quick Example

After installation, the category page at `GET /catalog/category/{id}` automatically applies any `filter` query parameters to the product listing and renders a facet sidebar:

```
GET /catalog/category/5?filter[color][]=red&filter[color][]=blue&filter[size][]=L
```

PHP natively expands the bracketed `filter` array into:

```php
['color' => ['red', 'blue'], 'size' => ['L']]
```

The `FilterParamParser` reads this from the request, validates each key against the known facetable attribute codes, and produces a `FilterSelection` that is forwarded to `LayeredNavigationAssembler`.

```php
<?php

declare(strict_types=1);

use Markommerce\CatalogAttributeStorefront\LayeredNavigation\FilterParamParser;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\LayeredNavigationAssembler;
use Markommerce\Catalog\Pagination\ResolvedPaginationOptions;

// Parse filter params from the HTTP request
$selection = $filterParamParser->parse($request);

// Assemble the filtered product page + disjunctive facets + active filters
$navData = $layeredNavigationAssembler->forCategory(
    categoryId: $category->id,
    options: $resolvedPaginationOptions,
    selection: $selection,
);

// $navData->page           — filtered Page<Product>
// $navData->facets         — list<LabeledFacet> with scope-resolved labels
// $navData->activeFilters  — list<ActiveFilter> for the active filter chips
```

## Query-Parameter Convention

Filters use the bracketed `filter` array convention:

```
?filter[{code}][]={value1}&filter[{code}][]={value2}&filter[{other}][]={value}
```

- Multiple values for the same attribute are **OR-ed** (a product matches if it has any of the selected values).
- Multiple attributes are **AND-ed** (a product must match all active attribute filters).
- Unknown attribute codes (not registered as facetable) are silently dropped.
- A scalar `filter[color]=red` form is accepted and normalised to `['red']`.
- The `page` parameter is reset when toggling a filter (via `FacetToggleUrlBuilder`).

## Layered Navigation Components

### `AttributeProductListFilter`

Implements `ProductListFilterInterface` from `markommerce/catalog`. Registered into `ProductListFilterRegistry` at boot. When `CategoryAssignmentService::paginatedProductsInCategory()` runs, it calls all registered filters — `AttributeProductListFilter` adds a correlated `EXISTS` sub-clause per active attribute code into the `catalog_product_attribute_index` table.

Filter logic:
- Multiple selected values for the same attribute → `value_text IN (...)` inside the EXISTS clause (OR semantics).
- Multiple active attribute codes → separate EXISTS clauses (AND semantics).
- The scope signature is resolved from the current `ScopeContext` against the attribute's `axes` config.

### `LayeredNavigationAssembler`

Implements `LayeredNavigationAssemblerInterface` (defined in `catalog-storefront`). Orchestrates:

1. `CategoryAssignmentService::paginatedProductsInCategory()` — filtered product page.
2. `AttributeFacetQuery::facets()` — disjunctive facet value counts from `catalog-attribute-index`.
3. Label enrichment — resolves scope-aware display labels for `select`/`multiselect` facet values via `ScopedOptionLabelResolver`.
4. Active filters — builds `ActiveFilter` objects from the current selection with resolved labels.

Returns `LayeredNavigationData` carrying the product page, `list<LabeledFacet>`, and `list<ActiveFilter>`.

### `FilterParamParser`

Parses the `filter[...]` query-param namespace from the HTTP request into a `FilterSelection`. Only keys matching a known facetable product attribute code pass through; unknown keys are dropped silently.

### `FacetToggleUrlBuilder`

Builds toggle URLs for facet values. Given the base category URL, the current query params, an attribute code, and a value, returns a new URL with that value added or removed from the active filter selection. Other params (`sort`, `size`, other attribute codes) are preserved; the `page` param is always reset so filter changes start from page 1.

## Disjunctive Faceting

Facet counts are computed **disjunctively**: for each attribute, all other active filters are applied but the attribute's own filter is ignored. This means all possible values for a facet remain visible and countable even when that facet is already filtered — matching standard e-commerce behavior (e.g. selecting "Red" still shows "Blue (4)" and "Green (2)" for the color facet).

The disjunctive logic is handled by `AttributeFacetQuery` from `markommerce/catalog-attribute-index`.

## Scope Awareness

Both `AttributeProductListFilter` and `LayeredNavigationAssembler` resolve scope signatures from the active `ScopeContext` using `SignatureCandidateEnumerator`. The most-specific candidate signature with rows in the index wins; the base (`''`) signature is used as a fallback. This ensures filter and facet counts reflect the correct scoped attribute values for the current locale or market.

## Facet Sidebar Template

The Latte component template at `resources/views/components/facet-sidebar.latte` renders:

- An **active filters** section (badge per selected value, when any filter is active).
- A **facet group** per attribute, each listing `LabeledFacetValue` items with display label, product count, and a toggle link (add/remove).

The template consumes `$facets` (list of `LabeledFacet`) and `$activeFilters` (list of `ActiveFilter`) from the component data, as populated by `ProductGridComponent` via the assembler.

## Integration with `catalog-storefront`

`catalog-storefront` defines `LayeredNavigationAssemblerInterface` and provides a `NullLayeredNavigationAssembler` (returns an empty page) bound by default. Installing `catalog-attribute-storefront` replaces the binding with the real `LayeredNavigationAssembler`, so the category page gains full layered navigation with no changes to application code.

`ProductGridData` carries `$facets` and `$activeFilters` fields (both `list<object>`, populated from the assembler). When this package is absent, both fields are empty arrays. When it is present, they hold `LabeledFacet` and `ActiveFilter` instances respectively.

## Module Bindings

`module.php` registers the following at boot:

| Binding | Notes |
|---|---|
| `LayeredNavigationAssemblerInterface` → `LayeredNavigationAssembler` | Overrides the null-object from `catalog-storefront` |
| `AttributeProductListFilter` | Registered into `ProductListFilterRegistry` at boot |
| `AttributeExistsClause` | Shared SQL builder; used by both the filter and the facet query |

## API Reference

### `AttributeProductListFilter`

Implements `ProductListFilterInterface`.

| Method | Description |
|---|---|
| `apply(RepositoryQueryBuilder $repositoryQueryBuilder, FilterSelection $filterSelection): void` | Add correlated EXISTS constraints to the product query for each active attribute filter key. No-op when the selection is empty. |

### `LayeredNavigationAssembler`

Implements `LayeredNavigationAssemblerInterface`.

| Method | Return type | Throws | Description |
|---|---|---|---|
| `forCategory(int $categoryId, ResolvedPaginationOptions $options, FilterSelection $selection)` | `LayeredNavigationData` | `CategoryNotFoundException` | Assemble filtered page + disjunctive facets + active filters for a category. |

### `FilterParamParser`

| Method | Return type | Description |
|---|---|---|
| `parse(Request $request): FilterSelection` | `FilterSelection` | Parse `filter[...]` query params into a `FilterSelection`. Unknown codes are dropped. |

### `FacetToggleUrlBuilder`

| Method | Return type | Description |
|---|---|---|
| `toggle(string $baseUrl, array $currentParams, string $code, string $value): string` | `string` | Build a URL that toggles the given attribute value in or out of the active filter selection. Resets `page`. |

### Value Objects

| Class | Description |
|---|---|
| `LabeledFacet` | Facet group with `code`, `type`, and `list<LabeledFacetValue>` `values`. Scope-resolved labels. |
| `LabeledFacetValue` | Single facet option: `value`, `label`, `count`, `selected`. |
| `ActiveFilter` | Active filter chip: `code`, `type`, `list<string>` `labels`, `list<string>` `values`. |
| `LayeredNavigationResult` | Thin result wrapper: `page`, `facets`, `activeFilters`. For direct use without the `ProductGridComponent`. |

## PHP Version and Code Conventions

Requires **PHP 8.5+**. All source files declare `strict_types=1`. No `final` classes — all classes are open for extension via Marko Preferences. Constructor injection only; no service locators or magic methods.

## Related Packages

- [markommerce/catalog-attribute-index](https://markommerce.dev/docs/packages/catalog-attribute-index/) — `AttributeFacetQuery`, `AttributeExistsClause`, and the `catalog_product_attribute_index` table used by this package for filtering and faceting
- [markommerce/catalog-storefront](https://markommerce.dev/docs/packages/catalog-storefront/) — defines `LayeredNavigationAssemblerInterface` and `ProductGridData`; this package provides the real implementation
- [markommerce/catalog](https://markommerce.dev/docs/packages/catalog/) — `ProductListFilterInterface`, `ProductListFilterRegistry`, `FilterSelection`; `CategoryAssignmentService::paginatedProductsInCategory()` applies registered filters
- [markommerce/catalog-attribute-scope](https://markommerce.dev/docs/packages/catalog-attribute-scope/) — `ScopedOptionLabelResolver` used to resolve scoped display labels for facet values
- [markommerce/scope](https://markommerce.dev/docs/packages/scope/) — `ScopeContext` and `SignatureCandidateEnumerator` used for scope-aware filter and facet resolution

## Documentation

Full usage, API reference, and examples: [markommerce/catalog-attribute-storefront](https://markommerce.dev/docs/packages/catalog-attribute-storefront/)
