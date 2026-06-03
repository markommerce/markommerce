<?php

declare(strict_types=1);

it(
    'creates packages/catalog-storefront-scope/README.md with title, intro paragraph, Installation, Quick Example, and Documentation footer sections',
    function (): void {
        $readmePath = dirname(__DIR__, 2) . '/README.md';
    
        expect(file_exists($readmePath))->toBeTrue();
    
        $content = file_get_contents($readmePath);
    
        expect($content)
            ->toContain('# markommerce/catalog-storefront-scope')
            ->toContain('composer require markommerce/catalog-storefront-scope')
            ->toContain('## Installation')
            ->toContain('## Quick Example')
            ->toContain('## Documentation')
            ->toContain('markommerce.dev/docs/packages/catalog-storefront-scope')
            ->toContain('Preference')
            ->toContain('ScopedProductGridComponent');
    }
);
