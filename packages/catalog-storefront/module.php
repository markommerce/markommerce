<?php

declare(strict_types=1);

use Markommerce\CatalogStorefront\Contracts\LayeredNavigationAssemblerInterface;
use Markommerce\CatalogStorefront\LayeredNavigation\NullLayeredNavigationAssembler;

/**
 * Default bindings for catalog-storefront.
 *
 * `LayeredNavigationAssemblerInterface` is bound to `NullLayeredNavigationAssembler`
 * so `ProductGridComponent` can always be resolved by the container, even when
 * `catalog-attribute-storefront` is not installed.  The attribute-storefront module
 * overrides this binding with the real `LayeredNavigationAssembler` when present.
 */
return [
    'bindings' => [
        LayeredNavigationAssemblerInterface::class => NullLayeredNavigationAssembler::class,
    ],
];
