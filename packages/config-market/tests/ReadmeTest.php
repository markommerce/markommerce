<?php

declare(strict_types=1);

it('ships a README.md for config-market documenting the placeholder status', function (): void {
    $readmePath = dirname(__DIR__) . '/README.md';

    expect(file_exists($readmePath))->toBeTrue();

    $content = (string) file_get_contents($readmePath);

    expect($content)
        ->toContain('# markommerce/config-market')
        ->toContain('## Installation')
        ->toContain('composer require markommerce/config-market')
        ->toContain('## Placeholder Status')
        ->toContain('FEATURES.md')
        ->toContain('## Documentation')
        ->toContain('markommerce.dev/docs/packages/config-market');
});
