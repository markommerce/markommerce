<?php

declare(strict_types=1);

it('follows DOCS-STANDARDS.md sectioning conventions for each new README', function (): void {
    $packages = [
        'catalog-scope',
        'locale',
        'catalog-locale',
    ];

    foreach ($packages as $package) {
        $readmePath = dirname(__DIR__, 3) . "/{$package}/README.md";

        expect(file_exists($readmePath))->toBeTrue("README.md missing for {$package}");

        $content = file_get_contents($readmePath);

        // Must start with h1 title matching the package name
        expect($content)->toContain("# markommerce/{$package}");

        // Must have Installation section with composer require command
        expect($content)->toContain('## Installation')
            ->and($content)->toContain("composer require markommerce/{$package}");

        // Must have a Quick Example section
        expect($content)->toContain('## Quick Example');

        // Must have a Documentation section linking to docs site
        expect($content)->toContain('## Documentation')
            ->and($content)->toContain('markommerce.dev/docs/packages/');

        // Must NOT have an Overview section (DOCS-STANDARDS forbids it)
        expect($content)->not->toContain('## Overview');
    }
});
