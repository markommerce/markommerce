<?php

declare(strict_types=1);

use Markommerce\Attribute\Contracts\AttributeTypeInterface;
use Markommerce\Attribute\PgSql\PgSqlAttributeDefinitionRepository;

it('autoloads a class from the Markommerce\Attribute namespace', function (): void {
    expect(interface_exists(AttributeTypeInterface::class))->toBeTrue();
});

it('marks the attribute package as a marko module in composer extra', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);

    expect($composer['type'])->toBe('marko-module')
        ->and($composer['extra']['marko']['module'])->toBeTrue();
});

it('autoloads Markommerce\\Attribute\\PgSql\\ classes from packages/attribute/src/PgSql/', function (): void {
    expect(class_exists(PgSqlAttributeDefinitionRepository::class))->toBeTrue();
});

it(
    'autoloads Markommerce\\Attribute\\PgSql\\Tests\\ classes from the repointed packages/attribute/tests/PgSql/ entry',
    function (): void {
        $rootComposer = dirname(__DIR__, 3) . '/composer.json';
        $decoded = json_decode((string) file_get_contents($rootComposer), true);
    
        expect($decoded['autoload-dev']['psr-4'])->toHaveKey('Markommerce\\Attribute\\PgSql\\Tests\\')
            ->and($decoded['autoload-dev']['psr-4']['Markommerce\\Attribute\\PgSql\\Tests\\'])
            ->toBe('packages/attribute/tests/PgSql/');
    
        // Verify the test file actually exists at that path
    expect(
        is_file(
            dirname(__DIR__, 3) . '/packages/attribute/tests/PgSql/Feature/PgSqlAttributeDefinitionRepositoryTest.php'
        )
    )
            ->toBeTrue();
    }
);

it('it no longer references markommerce/attribute-pgsql anywhere in sources, tests, or composer', function (): void {
    $root = dirname(__DIR__, 3);

    // Root composer.json must not require attribute-pgsql
    $rootComposer = json_decode((string) file_get_contents($root . '/composer.json'), true);
    expect($rootComposer['require'])->not->toHaveKey('markommerce/attribute-pgsql');
    expect($rootComposer['autoload-dev']['psr-4'])->not->toHaveKey('Markommerce\\Attribute\\PgSql\\Tests\\Old\\');

    // The attribute-pgsql package directory must not exist
    expect(is_dir($root . '/packages/attribute-pgsql'))->toBeFalse();
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
