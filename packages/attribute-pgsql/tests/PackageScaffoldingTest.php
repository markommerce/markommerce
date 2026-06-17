<?php

declare(strict_types=1);
use Markommerce\Attribute\PgSql\PgSqlAttributeDefinitionRepository;

it('autoloads a class from the Markommerce\Attribute\PgSql namespace', function (): void {
    expect(class_exists(PgSqlAttributeDefinitionRepository::class))->toBeTrue();
});

it('marks the attribute-pgsql package as a marko module in composer extra', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);

    expect($composer['type'])->toBe('marko-module')
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});

it('declares markommerce/attribute as a dependency of attribute-pgsql', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('markommerce/attribute')
        ->and($composer['require']['markommerce/attribute'])->toBe('self.version');
});

it('documents the attribute-pgsql tables and binding in its README', function (): void {
    $readme = (string) file_get_contents(dirname(__DIR__) . '/README.md');

    expect($readme)
        ->toContain('markommerce/attribute-pgsql')
        ->toContain('attribute_definitions')
        ->toContain('attribute_options')
        ->toContain('AttributeDefinitionRepositoryInterface')
        ->toContain('## Installation')
        ->toContain('## Documentation');
});
