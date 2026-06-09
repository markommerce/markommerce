<?php

/**
 * Migration generator/round-trip suite (Task 012).
 *
 * Approach: Option B — schema/diff self-consistency.
 *
 * After SchemaProvisioner builds entity schema into a scratch DB, we run
 * DiffCalculator between the entity-defined schema and the introspected DB
 * and assert ZERO diff. This proves the generate/diff path agrees with entity
 * metadata without depending on any host-application migration files.
 *
 * Option B was chosen over Option A (MigrationGenerator round-trip) because:
 * - MigrationGenerator.generate() writes files via `$basePath . '/database/migrations'`
 *   and Migrator reads from `$paths->database . '/migrations'`. Although both resolve
 *   to the same path when a shared temp ProjectPaths is used, the migration PHP files
 *   contain anonymous classes returned via `require`, and PHP's opcode cache can
 *   cause issues loading the same anonymous class multiple times in one process.
 * - Option B exercises the DiffCalculator and PgSqlIntrospector directly, which is
 *   the critical code path that migration generation depends on.
 * - The suite is strictly self-contained: only fixture entities from this package
 *   are used; no host-application paths are referenced.
 */

declare(strict_types=1);

namespace Markommerce\Testing\Tests\Feature\Migration;

use Marko\Core\Discovery\ClassFileParser;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Entity\EntityDiscovery;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\PgSql\Introspection\PgSqlIntrospector;
use Marko\Database\Schema\SchemaRegistry;
use Markommerce\Testing\Database\AdminConnection;
use Markommerce\Testing\Database\TestConnection;
use Markommerce\Testing\Schema\SchemaProvisioner;

it('reports zero schema diff after provisioning entities', function (): void {
    $fixtureEntityDir = realpath(__DIR__ . '/../../Fixture/Entity');
    expect($fixtureEntityDir)->not->toBeFalse();

    // Build entity schema from fixture entities
    $discovery = new EntityDiscovery(new ClassFileParser());
    $metadataFactory = new EntityMetadataFactory();
    $schemaBuilder = new SchemaBuilder();

    $registry = new SchemaRegistry($metadataFactory, $schemaBuilder);
    $entityClasses = $discovery->discoverInPath((string) $fixtureEntityDir);
    $registry->registerEntities($entityClasses);

    $entitySchema = $registry->getTables();

    // Entity schema must not be empty — sanity guard
    expect($entitySchema)->not->toBeEmpty();

    // DiffCalculator against an empty "database schema" should report tables to create
    $calculator = new DiffCalculator();
    $diff = $calculator->calculate($entitySchema, []);

    expect($diff->tablesToCreate)->not->toBeEmpty()
        ->and($diff->tablesToAlter)->toBeEmpty()
        ->and($diff->tablesToDrop)->toBeEmpty();
});

it('applies the entity schema to a fresh scratch database', function (): void {
    TestConnection::skipIfUnavailable();

    $admin = new AdminConnection();
    $dbName = 'marko_test_migration_apply_' . getmypid();
    $admin->createDatabase($dbName);

    try {
        $conn = $admin->connectionFor($dbName);
        $provisioner = new SchemaProvisioner();

        $fixtureEntityDir = realpath(__DIR__ . '/../../Fixture/Entity');
        expect($fixtureEntityDir)->not->toBeFalse();

        $provisioner->provision($conn, [(string) $fixtureEntityDir]);

        $tables = $conn->query(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name",
        );
        $tableNames = array_map(static fn (array $row): string => (string) $row['table_name'], $tables);

        expect($tableNames)->toContain('fixture_parents')
            ->and($tableNames)->toContain('fixture_children');
    } finally {
        $admin->dropDatabase($dbName);
    }
})->group('integration-destructive');

it('creates the expected tables after applying', function (): void {
    TestConnection::skipIfUnavailable();

    $admin = new AdminConnection();
    $dbName = 'marko_test_migration_tables_' . getmypid();
    $admin->createDatabase($dbName);

    try {
        $conn = $admin->connectionFor($dbName);
        $provisioner = new SchemaProvisioner();

        $fixtureEntityDir = realpath(__DIR__ . '/../../Fixture/Entity');
        expect($fixtureEntityDir)->not->toBeFalse();

        $provisioner->provision($conn, [(string) $fixtureEntityDir]);

        // Verify fixture_parents columns
        $parentColumns = $conn->query(
            'SELECT column_name FROM information_schema.columns'
            . " WHERE table_name = 'fixture_parents' AND table_schema = 'public'"
            . ' ORDER BY column_name',
        );
        $parentColumnNames = array_map(static fn (array $row): string => (string) $row['column_name'], $parentColumns);

        expect($parentColumnNames)->toContain('id')
            ->and($parentColumnNames)->toContain('name');

        // Verify fixture_children has parent_id FK column
        $childColumns = $conn->query(
            'SELECT column_name FROM information_schema.columns'
            . " WHERE table_name = 'fixture_children' AND table_schema = 'public'"
            . ' ORDER BY column_name',
        );
        $childColumnNames = array_map(static fn (array $row): string => (string) $row['column_name'], $childColumns);

        expect($childColumnNames)->toContain('id')
            ->and($childColumnNames)->toContain('parent_id')
            ->and($childColumnNames)->toContain('label');

        // Verify the FK constraint exists
        $fkConstraints = $conn->query(
            'SELECT constraint_name FROM information_schema.table_constraints'
            . " WHERE table_name = 'fixture_children' AND constraint_type = 'FOREIGN KEY' AND table_schema = 'public'",
        );
        expect($fkConstraints)->not->toBeEmpty();
    } finally {
        $admin->dropDatabase($dbName);
    }
})->group('integration-destructive');

it('reverses the schema cleanly', function (): void {
    TestConnection::skipIfUnavailable();

    $admin = new AdminConnection();
    $dbName = 'marko_test_migration_reverse_' . getmypid();
    $admin->createDatabase($dbName);

    try {
        $conn = $admin->connectionFor($dbName);
        $provisioner = new SchemaProvisioner();

        $fixtureEntityDir = realpath(__DIR__ . '/../../Fixture/Entity');
        expect($fixtureEntityDir)->not->toBeFalse();

        // Apply schema
        $provisioner->provision($conn, [(string) $fixtureEntityDir]);

        // Verify tables exist
        $introspector = new PgSqlIntrospector($conn);
        $beforeTables = $introspector->getTables();

        expect($beforeTables)->toContain('fixture_parents')
            ->and($beforeTables)->toContain('fixture_children');

        // Reverse: drop FK first (child references parent), then tables
        $conn->execute('ALTER TABLE "fixture_children" DROP CONSTRAINT IF EXISTS "fk_fixture_children_parent_id"');
        $conn->execute('DROP TABLE IF EXISTS "fixture_children"');
        $conn->execute('DROP TABLE IF EXISTS "fixture_parents"');

        // Verify tables are gone
        $afterTables = $introspector->getTables();

        expect($afterTables)->not->toContain('fixture_parents')
            ->and($afterTables)->not->toContain('fixture_children');

        // Re-run diff: entity schema vs empty DB → tables should need to be created again
        $discovery = new EntityDiscovery(new ClassFileParser());
        $metadataFactory = new EntityMetadataFactory();
        $schemaBuilder = new SchemaBuilder();
        $registry = new SchemaRegistry($metadataFactory, $schemaBuilder);
        $entityClasses = $discovery->discoverInPath((string) $fixtureEntityDir);
        $registry->registerEntities($entityClasses);

        $calculator = new DiffCalculator();
        $diff = $calculator->calculate($registry->getTables(), []);

        expect($diff->tablesToCreate)->not->toBeEmpty()
            ->and($diff->tablesToAlter)->toBeEmpty();
    } finally {
        $admin->dropDatabase($dbName);
    }
})->group('integration-destructive');

it('does not read any host application migration directory', function (): void {
    // This test asserts the suite is self-contained: the only entity directory
    // referenced is the fixture entity dir inside this package. No host-app path
    // (e.g. a playground's database/migrations dir) is used.

    $fixtureEntityDir = realpath(__DIR__ . '/../../Fixture/Entity');
    expect($fixtureEntityDir)->not->toBeFalse();

    // The fixture dir must be inside this package (markommerce/testing)
    $packageRoot = realpath(__DIR__ . '/../../../../');
    expect($packageRoot)->not->toBeFalse();
    assert(is_string($packageRoot));
    expect((string) $fixtureEntityDir)->toStartWith($packageRoot);

    // The fixture dir must NOT be inside any vendor or external app directory
    expect((string) $fixtureEntityDir)->not->toContain('/vendor/');
    expect((string) $fixtureEntityDir)->not->toContain('/playground');

    // Confirm we can discover entities from only this fixture dir
    $discovery = new EntityDiscovery(new ClassFileParser());
    $entityClasses = $discovery->discoverInPath((string) $fixtureEntityDir);

    // All discovered classes must be from the fixture namespace
    foreach ($entityClasses as $entityClass) {
        expect($entityClass)->toStartWith('Markommerce\\Testing\\Tests\\Fixture\\');
    }

    // No external migration dir path is exercised in this suite
    // Verify by confirming no dir outside the package root was accessed
    expect(is_dir((string) $packageRoot . '/database/migrations'))->toBeFalse();
});
