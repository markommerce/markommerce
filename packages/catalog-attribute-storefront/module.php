<?php

declare(strict_types=1);

use Markommerce\Catalog\Filtering\ProductListFilterRegistry;
use Markommerce\CatalogAttributeStorefront\Filter\AttributeProductListFilter;
use Markommerce\CatalogAttributeStorefront\LayeredNavigation\LayeredNavigationAssembler;

return [
    'require' => [
        'marko/core'                          => '*',
        'marko/database'                      => '*',
        'markommerce/attribute'               => '*',
        'markommerce/attribute-scope'         => '*',
        'markommerce/catalog'                 => '*',
        'markommerce/catalog-attribute'       => '*',
        'markommerce/catalog-attribute-index' => '*',
        'markommerce/catalog-attribute-scope' => '*',
        'markommerce/catalog-storefront'      => '*',
        'markommerce/criteria'                => '*',
        'markommerce/scope'                   => '*',
    ],
    // NOTE: LayeredNavigationAssemblerInterface is bound to NullLayeredNavigationAssembler by
    // catalog-storefront (so it resolves standalone). We do NOT re-bind the interface here — Marko
    // forbids two modules binding the same interface. Instead LayeredNavigationAssembler declares
    // #[Preference(replaces: NullLayeredNavigationAssembler::class)], so when this package is
    // installed the container swaps the null object for the real assembler globally.
    // AttributeExistsClause is a concrete class owned + bound by catalog-attribute-index — do NOT
    // re-bind it here (Marko forbids two modules binding the same key). It auto-resolves anyway.
    'bindings' => [
        LayeredNavigationAssembler::class => LayeredNavigationAssembler::class,
        AttributeProductListFilter::class => AttributeProductListFilter::class,
    ],
    // Eager registration: AttributeProductListFilter's deps (AttributeDefinitionRepositoryInterface,
    // SignatureCandidateEnumerator, ScopeContext, AttributeExistsClause) are all lightweight
    // request-scoped objects — no heavy I/O at construction time — so we resolve eagerly at boot.
    'boot' => function (
        ProductListFilterRegistry $registry,
        AttributeProductListFilter $attributeProductListFilter,
    ): void {
        $registry->register($attributeProductListFilter);
    },
];
