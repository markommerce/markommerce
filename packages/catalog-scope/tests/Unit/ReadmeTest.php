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
