<?php

declare(strict_types=1);

it('ships a README.md in packages/catalog-locale with installation, boot-bridge example, and docs link', function (): void {
    $readmePath = dirname(__DIR__) . '/README.md';

    expect(file_exists($readmePath))->toBeTrue();

    $content = file_get_contents($readmePath);

    expect($content)
        ->toContain('# markommerce/catalog-locale')
        ->toContain('## Installation')
        ->toContain('composer require markommerce/catalog-locale')
        ->toContain('## Quick Example')
        ->toContain('boot')
        ->toContain('ScopedFieldRegistry')
        ->toContain('## Documentation')
        ->toContain('markommerce.dev/docs/packages/catalog-locale');
});
