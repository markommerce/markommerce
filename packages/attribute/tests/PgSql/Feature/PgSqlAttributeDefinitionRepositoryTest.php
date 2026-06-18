<?php

declare(strict_types=1);

namespace Markommerce\Attribute\PgSql\Tests\Feature;

use Marko\Database\Connection\ConnectionInterface;
use Markommerce\Attribute\Contracts\AttributeDefinitionRepositoryInterface;
use Markommerce\Attribute\Entity\AttributeDefinition;
use Markommerce\Attribute\Entity\AttributeOption;
use Markommerce\Attribute\PgSql\PgSqlAttributeDefinitionRepository;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\IntegrationTestCase;
use Markommerce\Testing\Profile\StoreProfile;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function attributePgsqlVendorDir(): string
{
    return dirname(__DIR__, 5) . '/vendor';
}

function makeAttributePgsqlProfile(): StoreProfile
{
    return StoreProfile::of(
        attributePgsqlVendorDir(),
        'markommerce/attribute',
        'marko/database-pgsql',
    );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('it provisions the attribute_definitions and attribute_options tables from the entities', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeAttributePgsqlProfile());
    $testCase->setUpIntegration();

    try {
        /** @var ConnectionInterface $connection */
        $connection = $testCase->get(ConnectionInterface::class);

        $rows = $connection->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name IN ('attribute_definitions', 'attribute_options')",
        );

        $tableNames = array_column($rows, 'table_name');
        sort($tableNames);

        expect($tableNames)->toBe(['attribute_definitions', 'attribute_options']);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it persists and reloads an attribute definition with its config jsonb', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeAttributePgsqlProfile());
    $testCase->setUpIntegration();

    try {
        /** @var AttributeDefinitionRepositoryInterface $repository */
        $repository = $testCase->get(AttributeDefinitionRepositoryInterface::class);

        $definition = new AttributeDefinition();
        $definition->code = 'color';
        $definition->entityType = 'product';
        $definition->type = 'select';
        $definition->label = 'Color';
        $definition->config = ['max_values' => 3, 'display' => 'swatch'];

        $repository->save($definition);

        expect($definition->id)->not->toBeNull();

        $found = $repository->find($definition->id);

        $foundConfig = $found->config;
        ksort($foundConfig);

        expect($found)->not->toBeNull()
            ->and($found->code)->toBe('color');

        expect($foundConfig)->toBe(['display' => 'swatch', 'max_values' => 3]);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it cascade-deletes options when a definition is deleted', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeAttributePgsqlProfile());
    $testCase->setUpIntegration();

    try {
        /** @var AttributeDefinitionRepositoryInterface $repository */
        $repository = $testCase->get(AttributeDefinitionRepositoryInterface::class);

        $definition = new AttributeDefinition();
        $definition->code = 'size';
        $definition->entityType = 'product';
        $definition->type = 'select';
        $definition->label = 'Size';

        $repository->save($definition);
        $defId = $definition->id;

        $option = new AttributeOption();
        $option->attributeId = $defId;
        $option->value = 'XL';
        $option->label = 'Extra Large';

        $repository->saveOption($option);
        $repository->delete($definition);

        expect($repository->find($defId))->toBeNull();

        $ghost = new AttributeDefinition();
        $ghost->id = $defId;

        expect($repository->optionsFor($ghost))->toHaveCount(0);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it finds a definition by entity type and code', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeAttributePgsqlProfile());
    $testCase->setUpIntegration();

    try {
        /** @var AttributeDefinitionRepositoryInterface $repository */
        $repository = $testCase->get(AttributeDefinitionRepositoryInterface::class);

        $definition = new AttributeDefinition();
        $definition->code = 'weight';
        $definition->entityType = 'product';
        $definition->type = 'decimal';
        $definition->label = 'Weight';

        $repository->save($definition);

        $found = $repository->findByCode('product', 'weight');

        expect($found)->not->toBeNull()
            ->and($found->code)->toBe('weight')
            ->and($found->entityType)->toBe('product');

        expect($repository->findByCode('product', 'nonexistent'))->toBeNull();
        expect($repository->findByCode('category', 'weight'))->toBeNull();
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it round-trips select options for a definition', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeAttributePgsqlProfile());
    $testCase->setUpIntegration();

    try {
        /** @var AttributeDefinitionRepositoryInterface $repository */
        $repository = $testCase->get(AttributeDefinitionRepositoryInterface::class);

        $definition = new AttributeDefinition();
        $definition->code = 'color';
        $definition->entityType = 'product';
        $definition->type = 'select';
        $definition->label = 'Color';

        $repository->save($definition);

        $red = new AttributeOption();
        $red->attributeId = $definition->id;
        $red->value = 'red';
        $red->label = 'Red';
        $red->position = 0;

        $blue = new AttributeOption();
        $blue->attributeId = $definition->id;
        $blue->value = 'blue';
        $blue->label = 'Blue';
        $blue->position = 1;

        $repository->saveOption($red);
        $repository->saveOption($blue);

        $options = $repository->optionsFor($definition);

        expect($options)->toHaveCount(2);

        $values = array_column($options, 'value');
        sort($values);

        expect($values)->toBe(['blue', 'red']);

        $repository->deleteOptionsFor($definition);

        expect($repository->optionsFor($definition))->toHaveCount(0);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it satisfies the attribute definition repository contract with the PgSql driver', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeAttributePgsqlProfile());
    $testCase->setUpIntegration();

    try {
        $repository = $testCase->get(AttributeDefinitionRepositoryInterface::class);

        expect($repository)->toBeInstanceOf(PgSqlAttributeDefinitionRepository::class)
            ->and($repository)->toBeInstanceOf(AttributeDefinitionRepositoryInterface::class);
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');

it('it reads attribute definitions via the in-package PgSql repository', function (): void {
    TestConnection::skipIfUnavailable();

    $testCase = new IntegrationTestCase(makeAttributePgsqlProfile());
    $testCase->setUpIntegration();

    try {
        /** @var AttributeDefinitionRepositoryInterface $repository */
        $repository = $testCase->get(AttributeDefinitionRepositoryInterface::class);

        expect($repository)->toBeInstanceOf(PgSqlAttributeDefinitionRepository::class);

        $definition = new AttributeDefinition();
        $definition->code = 'material';
        $definition->entityType = 'product';
        $definition->type = 'text';
        $definition->label = 'Material';

        $repository->save($definition);

        $found = $repository->findByCode('product', 'material');

        expect($found)->not->toBeNull()
            ->and($found->code)->toBe('material');
    } finally {
        $testCase->tearDownIntegration();
        $testCase->tearDownClass();
    }
})->group('integration-destructive');
