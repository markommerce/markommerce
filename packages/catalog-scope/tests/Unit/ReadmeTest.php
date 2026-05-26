<?php

declare(strict_types=1);

it('ships a README.md in packages/catalog-scope with installation, quick example, and docs link', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';

    expect(file_exists($readmePath))->toBeTrue();

    $content = file_get_contents($readmePath);

    expect($content)
        ->toContain('# markommerce/catalog-scope')
        ->toContain('## Installation')
        ->toContain('composer require markommerce/catalog-scope')
        ->toContain('## Quick Example')
        ->toContain('## Documentation')
        ->toContain('markommerce.dev/docs/packages/catalog-scope');
});

it('it trims packages/catalog-scope/README.md to remove the ScopedProductGridComponent section, replacing it with a cross-link to catalog-storefront-scope', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)
        ->toContain('catalog-storefront-scope')
        ->not->toContain('ScopedProductGridComponent');
});
