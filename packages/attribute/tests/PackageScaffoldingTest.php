<?php

declare(strict_types=1);
use Markommerce\Attribute\Contracts\AttributeTypeInterface;

it('autoloads a class from the Markommerce\Attribute namespace', function (): void {
    expect(interface_exists(AttributeTypeInterface::class))->toBeTrue();
});

it('marks the attribute package as a marko module in composer extra', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);

    expect($composer['type'])->toBe('marko-module')
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});

it('documents the attribute package purpose and type registration in its README', function (): void {
    $readme = (string) file_get_contents(dirname(__DIR__) . '/README.md');

    expect($readme)
        ->toContain('markommerce/attribute')
        ->toContain('AttributeTypeInterface')
        ->toContain('AttributeTypeRegistry')
        ->toContain('AttributeDefinitionInterface')
        ->toContain('## Installation')
        ->toContain('## Quick Example')
        ->toContain('## Documentation');
});
