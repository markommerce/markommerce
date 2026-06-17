<?php

declare(strict_types=1);

namespace Markommerce\CatalogAttributeStorefront\LayeredNavigation;

/**
 * Builds toggle URLs for facet values on the category page.
 *
 * Given the base category URL, the current query params, an attribute code, and a value,
 * returns a new URL with that value added to the filter selection (if absent) or removed
 * (if already selected). Other params (sort, size, other filter codes) are preserved.
 * The page param is intentionally reset so filter changes always start from page 1.
 */
readonly class FacetToggleUrlBuilder
{
    /**
     * Build a toggle URL that adds or removes the given filter value.
     *
     * @param string               $baseUrl       The category base URL (e.g. '/catalog/category/1')
     * @param array<string, mixed> $currentParams The current query params (sort, size, filter, etc.)
     * @param string               $code          The attribute code to toggle (e.g. 'color')
     * @param string               $value         The attribute value to add or remove (e.g. 'red')
     */
    public function toggle(
        string $baseUrl,
        array $currentParams,
        string $code,
        string $value,
    ): string {
        $params = $currentParams;

        // Always reset pagination when toggling a filter
        unset($params['page']);

        /** @var array<string, array<array-key, string>> $filters */
        $filters = is_array($params['filter'] ?? null) ? $params['filter'] : [];

        /** @var list<string> $existing */
        $existing = is_array($filters[$code] ?? null) ? array_values($filters[$code]) : [];

        if (in_array($value, $existing, strict: true)) {
            // Remove the value from the selection
            $existing = array_values(array_filter($existing, fn (string $v): bool => $v !== $value));
        } else {
            // Add the value to the selection
            $existing[] = $value;
        }

        if ($existing === []) {
            unset($filters[$code]);
        } else {
            $filters[$code] = $existing;
        }

        if ($filters === []) {
            unset($params['filter']);
        } else {
            $params['filter'] = $filters;
        }

        $query = $params !== [] ? '?' . http_build_query($params) : '';

        return $baseUrl . $query;
    }
}
