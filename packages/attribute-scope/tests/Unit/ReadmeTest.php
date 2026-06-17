<?php

declare(strict_types=1);

it('documents the attribute-scope option-label scoping in its README', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';

    expect(file_exists($readmePath))->toBeTrue();

    $content = file_get_contents($readmePath);

    expect($content)
        ->toContain('# markommerce/attribute-scope')
        ->toContain('## Installation')
        ->toContain('composer require markommerce/attribute-scope')
        ->toContain('## Quick Example')
        ->toContain('AttributeOptionScopedLabels')
        ->toContain('ScopedOptionLabelResolver')
        ->toContain('## Documentation')
        ->toContain('markommerce.dev/docs/packages/attribute-scope');
});
