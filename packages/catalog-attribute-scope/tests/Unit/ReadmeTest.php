<?php

declare(strict_types=1);

it('documents the catalog-attribute-scope scoped values and accessor in its README', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';

    expect(file_exists($readmePath))->toBeTrue();

    $content = file_get_contents($readmePath);

    expect($content)
        ->toContain('# markommerce/catalog-attribute-scope')
        ->toContain('## Installation')
        ->toContain('composer require markommerce/catalog-attribute-scope')
        ->toContain('## Quick Example')
        ->toContain('ProductScopedAttributeValues')
        ->toContain('ScopedProductAttributeAccessor')
        ->toContain('## Documentation')
        ->toContain('markommerce.dev/docs/packages/catalog-attribute-scope');
});
