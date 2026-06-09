<?php

declare(strict_types=1);

it('ships a README that follows the project package README standards', function (): void {
    $readmePath = dirname(__DIR__) . '/README.md';

    expect(file_exists($readmePath))->toBeTrue();

    $content = file_get_contents($readmePath);

    expect($content)
        ->toContain('# markommerce/market')
        ->toContain('## Installation')
        ->toContain('composer require markommerce/market')
        ->toContain('## Quick Example')
        ->toContain('market')
        ->toContain('axes')
        ->toContain('## Documentation')
        ->toContain('markommerce.dev/docs/packages/market');
});

it(
    'markommerce/market README declares installation, quick example, and documentation link sections per the project standard',
    function (): void {
        $readmePath = dirname(__DIR__) . '/README.md';
        $content = file_get_contents($readmePath);
    
        expect($content)
            ->toContain('## Installation')
            ->toContain('composer require markommerce/market')
            ->toContain('## Quick Example')
            ->toContain('## Documentation')
            ->toContain('markommerce.dev/docs/packages/market');
    }
);
