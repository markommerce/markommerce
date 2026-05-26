<?php

declare(strict_types=1);

it('ships a README.md in packages/locale with installation, axis-config example, and docs link', function (): void {
    $readmePath = dirname(__DIR__) . '/README.md';

    expect(file_exists($readmePath))->toBeTrue();

    $content = file_get_contents($readmePath);

    expect($content)
        ->toContain('# markommerce/locale')
        ->toContain('## Installation')
        ->toContain('composer require markommerce/locale')
        ->toContain('## Quick Example')
        ->toContain('locale')
        ->toContain('axes')
        ->toContain('## Documentation')
        ->toContain('markommerce.dev/docs/packages/locale');
});
