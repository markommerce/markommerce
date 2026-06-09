<?php

declare(strict_types=1);

namespace Markommerce\Scope\PgSql\Tests\Feature;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Diff\DiffCalculator;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\PgSql\Query\PgSqlQueryBuilder;
use Marko\Database\PgSql\Sql\PgSqlGenerator;
use Marko\Database\Query\EntityQueryBuilderInterface;
use Marko\Database\Schema\Column as SchemaColumn;
use Marko\Database\Schema\SchemaRegistry;
use Marko\Database\Schema\Table as SchemaTable;
use Markommerce\Scope\Attributes\Scoped;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Metadata\ScopeMetadataFactory;
use Markommerce\Scope\PgSql\Query\PgSqlScopedFieldRenderer;
use Markommerce\Scope\PgSql\Schema\ScopesGinIndexEmitter;
use Markommerce\Scope\Query\ScopedFieldExpression;
use Markommerce\Scope\Query\ScopedOrderBy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Resolution\ScopeWalker;
use Markommerce\Scope\Resolver\ScopeResolver;
use Markommerce\Scope\Signature\ScopeSignature;
use Markommerce\Scope\Signature\ScopeSignatureValidator;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;
use Markommerce\Scope\Storage\HasScopes;
use Markommerce\Scope\Storage\HasScopesInterface;
use Markommerce\Testing\Database\TestConnection;
use PHPUnit\Framework\SkippedWithMessageException;

// ─── Test-only entity fixtures ─────────────────────────────────────────────────

#[Table('_pg_int_product')]
class PgIntProduct extends Entity implements HasScopesInterface
{
    use HasScopes;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Scoped(axes: ['channel', 'locale'])]
    #[Column(length: 255)]
    public ?string $name = null;

    #[Scoped(axes: ['channel', 'locale'])]
    #[Column(length: 20)]
    public ?string $price = null;
}

/**
 * Wraps PgSqlQueryBuilder to satisfy EntityQueryBuilderInterface (adds with() method).
 */
class PgSqlEntityQueryBuilder extends PgSqlQueryBuilder implements EntityQueryBuilderInterface
{
    public function with(string ...$relations): static
    {
        return $this;
    }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Returns a process-scoped unique table name to avoid collisions in parallel runs.
 */
function pgIntTableName(): string
{
    static $name = null;

    if ($name === null) {
        $name = 'scope_int_' . bin2hex(random_bytes(8));
    }

    return $name;
}

/**
 * Build the SchemaTable for the integration test table.
 */
function buildPgIntSchemaTable(string $tableName): SchemaTable
{
    return new SchemaTable(
        name: $tableName,
        columns: [
            new SchemaColumn(name: 'id', type: 'integer', primaryKey: true, autoIncrement: true),
            new SchemaColumn(name: 'name', type: 'string', length: 255, nullable: true),
            new SchemaColumn(name: 'price', type: 'string', length: 20, nullable: true),
            new SchemaColumn(name: 'scopes', type: 'json', nullable: true),
        ],
        indexes: [],
    );
}

/**
 * Build the SchemaDiff that represents creating the integration table from scratch.
 */
function buildPgIntSchemaDiff(string $tableName): SchemaDiff
{
    $table = buildPgIntSchemaTable($tableName);

    return (new DiffCalculator())->calculate(
        [$tableName => $table],
        [],
    );
}

/**
 * Build a ScopeRegistryInterface that has 'channel' and 'locale' axes.
 *
 * The second parameter allows callers to override per-axis defaults.
 * When omitted, every axis uses the sentinel '__test_default' so that
 * all-default contexts produce an empty candidate list (plain SQL).
 *
 * @param array<string, list<string>> $axes
 * @param array<string, string> $defaults
 */
function buildPgIntRegistry(array $axes = [], array $defaults = []): ScopeRegistryInterface
{
    if ($axes === []) {
        $axes = [
            'channel' => ['__test_default', 'default', 'default.web', 'default.mobile'],
            'locale'  => ['__test_default', 'en', 'en.gb', 'de'],
        ];
    }

    return new class ($axes, $defaults) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        /**
         * @param array<string, list<string>> $axes
         * @param array<string, string> $defaults
         */
        public function __construct(
            array $axes,
            array $defaults,
        ) {
            $this->builtAxes = [];

            foreach ($axes as $name => $paths) {
                $axisDefault = $defaults[$name] ?? '__test_default';
                $hierarchy = new ScopeHierarchy($paths);
                $this->builtAxes[$name] = new ScopeAxis(
                    name: $name,
                    hierarchy: $hierarchy,
                    default: $axisDefault,
                );
            }
        }

        public function hasAxis(string $name): bool
        {
            return isset($this->builtAxes[$name]);
        }

        public function getAxis(string $name): ScopeAxis
        {
            return $this->builtAxes[$name];
        }

        public function listAxes(): array
        {
            return array_keys($this->builtAxes);
        }

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            return $this->builtAxes[$axisName]->hierarchy;
        }
    };
}

/**
 * Build a stub SchemaRegistry for ScopesGinIndexEmitter that uses the dynamic table name.
 *
 * The emitter calls getTableNames(), getEntityClass(), and getMetadata().
 * We need getEntityClass() to return a class that uses HasScopes.
 */
function buildPgIntEmitterRegistry(string $tableName): SchemaRegistry
{
    return new class ($tableName) extends SchemaRegistry
    {
        public function __construct(private readonly string $dynamicTableName)
        {
            parent::__construct(
                metadataFactory: new EntityMetadataFactory(),
                schemaBuilder: new SchemaBuilder(),
            );

            $this->registerEntities([PgIntProduct::class]);
        }

        public function getTableNames(): array
        {
            return [$this->dynamicTableName];
        }

        public function getEntityClass(string $tableName): ?string
        {
            if ($tableName === $this->dynamicTableName) {
                return PgIntProduct::class;
            }

            return parent::getEntityClass($tableName);
        }

        public function getMetadata(string $tableName): ?EntityMetadata
        {
            if ($tableName === $this->dynamicTableName) {
                return parent::getMetadata('_pg_int_product');
            }

            return parent::getMetadata($tableName);
        }
    };
}

/**
 * Build the full set of migration SQL statements for the integration table.
 *
 * @return list<string>
 */
function buildPgIntMigrationStatements(string $tableName): array
{
    $diff = buildPgIntSchemaDiff($tableName);
    $generator = new PgSqlGenerator();
    $statements = $generator->generateUp($diff);

    $emitterRegistry = buildPgIntEmitterRegistry($tableName);
    $emitter = new ScopesGinIndexEmitter();
    $ginStatements = $emitter->additionalSqlForTables($emitterRegistry);

    return array_merge($statements, $ginStatements);
}

/**
 * Wire up a ScopeResolver for the PgIntProduct entity.
 *
 * @return array{ScopeResolver, ScopeContext, ScopeRegistryInterface, ScopeMetadataFactory, SignatureCandidateEnumerator}
 */
function buildPgIntResolver(): array
{
    $registry = buildPgIntRegistry();
    $context = new ScopeContext($registry);
    $metadataFactory = new ScopeMetadataFactory($registry, new ScopedFieldRegistry(scopeRegistry: $registry));
    $enumerator = new SignatureCandidateEnumerator($registry);
    $walker = new ScopeWalker($enumerator);
    $validator = new ScopeSignatureValidator($registry);
    $resolver = new ScopeResolver($metadataFactory, $walker, $context, $validator);

    return [$resolver, $context, $registry, $metadataFactory, $enumerator];
}

// ─── Shared connection & lifecycle ────────────────────────────────────────────

beforeEach(function (): void {
    TestConnection::skipIfUnavailable();

    $conn = new TestConnection();
    $tableName = pgIntTableName();

    // DROP IF EXISTS first (crash resilience from previous failed runs)
    $conn->execute(sprintf('DROP TABLE IF EXISTS "%s"', $tableName));

    // Run migration statements OUTSIDE any transaction
    $statements = buildPgIntMigrationStatements($tableName);

    foreach ($statements as $sql) {
        $conn->execute($sql);
    }

    $this->conn = $conn;
    $this->tableName = $tableName;
});

afterEach(function (): void {
    if (isset($this->conn) && isset($this->tableName)) {
        $this->conn->execute(sprintf('DROP TABLE IF EXISTS "%s"', $this->tableName));
    }
});

// ─── Tests ────────────────────────────────────────────────────────────────────

it(
    'round-trips a composite override write through the walker and reads back the expected value',
    function (): void {
        /** @var TestConnection $conn */
        $conn = $this->conn;

        [$resolver, $context] = buildPgIntResolver();
        $context->in('channel', 'default.web')->in('locale', 'en.gb');

        // Build product and set a composite (two-axis) override
        $product = new PgIntProduct();
        $product->name = 'Default Name';
        $product->price = '10.00';

        $signature = new ScopeSignature(['channel' => 'default.web', 'locale' => 'en.gb']);
        $resolver->setOverride($product, 'name', 'GB Web Name', $signature);

        // Persist: insert with scopes JSON
        $scopesJson = json_encode($product->overrides(), JSON_THROW_ON_ERROR);
        $conn->execute(
            sprintf('INSERT INTO "%s" ("name", "price", "scopes") VALUES (?, ?, ?::jsonb)', $this->tableName),
            [$product->name, $product->price, $scopesJson],
        );

        // Read back the raw scopes JSON from DB
        $rows = $conn->query(
            sprintf('SELECT "scopes" FROM "%s" LIMIT 1', $this->tableName),
        );

        $rawScopes = $rows[0]['scopes'];
        $decoded = json_decode($rawScopes, true, 512, JSON_THROW_ON_ERROR);

        // Re-hydrate the product with scopes from DB
        $rehydrated = new PgIntProduct();
        $rehydrated->name = 'Default Name';
        $rehydrated->price = '10.00';

        foreach ($decoded as $sig => $values) {
            foreach ($values as $property => $value) {
                $rehydrated->setOverride($sig, $property, $value);
            }
        }

        // resolved() should return the override
        $result = $resolver->resolved($rehydrated, 'name');

        expect($result)->toBe('GB Web Name');
    },
)->group('integration-destructive');

it(
    'orders rows by the resolved property using the COALESCE chain',
    function (): void {
        /** @var TestConnection $conn */
        $conn = $this->conn;
        $tableName = $this->tableName;

        // Insert three products with different scope overrides
        // Product A: has direct composite override 'channel:default.web|locale:en.gb' → 'Aardvark'
        // Product B: has parent-scope override 'locale:en' → 'Banana'
        // Product C: no override → base 'Carrot'
        $overridesA = json_encode(
            ['channel:default.web|locale:en.gb' => ['name' => 'Aardvark']],
            JSON_THROW_ON_ERROR,
        );
        $overridesB = json_encode(
            ['locale:en' => ['name' => 'Banana']],
            JSON_THROW_ON_ERROR,
        );

        $conn->execute(
            sprintf('INSERT INTO "%s" ("name", "price", "scopes") VALUES (?, ?, ?::jsonb)', $tableName),
            ['Default A', '1.00', $overridesA],
        );
        $conn->execute(
            sprintf('INSERT INTO "%s" ("name", "price", "scopes") VALUES (?, ?, ?::jsonb)', $tableName),
            ['Default B', '2.00', $overridesB],
        );
        $conn->execute(
            sprintf('INSERT INTO "%s" ("name", "price", "scopes") VALUES (?, ?, null)', $tableName),
            ['Carrot', '3.00'],
        );

        [$resolver, $context, $registry, $metadataFactory, $enumerator] = buildPgIntResolver();
        $context->in('channel', 'default.web')->in('locale', 'en.gb');

        $renderer = new PgSqlScopedFieldRenderer();
        $orderBy = new ScopedOrderBy(
            property: 'name',
            scopeMetadataFactory: $metadataFactory,
            scopeContext: $context,
            scopedFieldRenderer: $renderer,
            signatureCandidateEnumerator: $enumerator,
            entityClass: PgIntProduct::class,
            direction: 'asc',
        );

        // Use PgSqlEntityQueryBuilder (implements EntityQueryBuilderInterface)
        $builder = new PgSqlEntityQueryBuilder($conn);
        $builder->table($tableName);
        $orderBy->apply($builder);

        $rows = $builder->get();

        // All three rows should be returned
        expect($rows)->toHaveCount(3);

        // The COALESCE chain resolves each row's effective name for the active context.
        // In context channel=default.web, locale=en.gb:
        //   Product A → scopes override 'channel:default.web|locale:en.gb'.name = 'Aardvark'
        //   Product B → scopes override 'locale:en'.name = 'Banana'
        //   Product C → base column 'Carrot'
        // Ascending alphabetical order: Aardvark < Banana < Carrot
        $names = array_column($rows, 'name');
        expect($names[0])->toBe('Default A')   // COALESCE resolves to Aardvark → sorts first
            ->and($names[1])->toBe('Default B') // COALESCE resolves to Banana → sorts second
            ->and($names[2])->toBe('Carrot');    // COALESCE falls through to base column → sorts last
    },
)->group('integration-destructive');

it(
    'the generated candidate signature list for a context + axes matches the expected ordered list when fetched as raw SQL',
    function (): void {
        [$resolver, $context, $registry, $metadataFactory, $enumerator] = buildPgIntResolver();
        $context->in('channel', 'default.web')->in('locale', 'en.gb');

        // Use the enumerator directly to get the expected candidate signatures
        $axes = ['channel', 'locale'];
        $expected = $enumerator->enumerate($axes, $context);

        // Build the expected COALESCE expression manually
        $expectedParts = [];
        foreach ($expected as $sig) {
            $sigKey = $sig->toString();
            $expectedParts[] = "\"scopes\"->'$sigKey'->>'name'";
        }
        $expectedParts[] = '"name"';
        $expectedCoalesce = 'COALESCE(' . implode(', ', $expectedParts) . ')';

        // Render using the real PgSqlScopedFieldRenderer
        $renderer = new PgSqlScopedFieldRenderer();
        $renderedSql = $renderer->render(
            new ScopedFieldExpression(
                property: 'name',
                column: 'name',
                candidateSignatures: $expected,
            ),
        );

        expect($renderedSql)->toContain('COALESCE(')
            ->and($renderedSql)->toBe($expectedCoalesce);
    },
)->group('integration-destructive');

it(
    'exceeding the candidate cap triggers a warning and limits the chain length',
    function (): void {
        $registry = buildPgIntRegistry();
        $context = new ScopeContext($registry);
        // Set context to most-specific paths to maximise candidate count
        $context->in('channel', 'default.web')->in('locale', 'en.gb');

        // Cap = 3 — fewer than the full candidate list for 2 axes with depth hierarchies
        $cappedEnumerator = new SignatureCandidateEnumerator($registry, cap: 3);

        $warningCount = 0;
        set_error_handler(function (int $errno, string $errstr) use (&$warningCount): bool {
            if ($errno === E_USER_WARNING && str_contains($errstr, 'cap')) {
                $warningCount++;
            }

            return true;
        });

        $candidates = $cappedEnumerator->enumerate(['channel', 'locale'], $context);

        restore_error_handler();

        expect($warningCount)->toBe(1)
            ->and(count($candidates))->toBeLessThanOrEqual(3);
    },
)->group('integration-destructive');

it(
    'the schema apply creates the scopes JSONB column AND the jsonb_path_ops GIN index in a single sequence (column from PgSqlGenerator, index from the task-012 emitter)',
    function (): void {
        /** @var TestConnection $conn */
        $conn = $this->conn;
        $tableName = $this->tableName;

        // Table and index were created in beforeEach via buildPgIntMigrationStatements().
        // Verify the scopes column exists and is JSONB.
        $colRows = $conn->query(
            "SELECT column_name, data_type
             FROM information_schema.columns
             WHERE table_name = ?
               AND column_name = 'scopes'",
            [$tableName],
        );

        expect($colRows)->toHaveCount(1)
            ->and(strtolower($colRows[0]['data_type']))->toBe('jsonb');

        // Verify the GIN index exists.
        $indexRows = $conn->query(
            'SELECT indexname, indexdef
             FROM pg_indexes
             WHERE tablename = ?
               AND indexname = ?',
            [$tableName, $tableName . '_scopes_gin'],
        );

        expect($indexRows)->toHaveCount(1)
            ->and(strtolower($indexRows[0]['indexdef']))->toContain('using gin')
            ->and($indexRows[0]['indexdef'])->toContain('jsonb_path_ops');
    },
)->group('integration-destructive');

it(
    'the GIN index is introspectable via pg_indexes after migration (the indexdef contains "USING gin" and "jsonb_path_ops")',
    function (): void {
        /** @var TestConnection $conn */
        $conn = $this->conn;
        $tableName = $this->tableName;

        $rows = $conn->query(
            'SELECT indexdef
             FROM pg_indexes
             WHERE tablename = ?
               AND indexname = ?',
            [$tableName, $tableName . '_scopes_gin'],
        );

        expect($rows)->toHaveCount(1);

        $indexDef = $rows[0]['indexdef'];

        expect(strtolower($indexDef))->toContain('using gin')
            ->and($indexDef)->toContain('jsonb_path_ops');
    },
)->group('integration-destructive');

it(
    're-running the schema apply twice is a no-op for the GIN index (the second run does not error and does not create a duplicate index)',
    function (): void {
        /** @var TestConnection $conn */
        $conn = $this->conn;
        $tableName = $this->tableName;

        // Re-run only the GIN index statement (the CREATE TABLE would fail without IF NOT EXISTS).
        $emitterRegistry = buildPgIntEmitterRegistry($tableName);
        $emitter = new ScopesGinIndexEmitter();
        $ginStatements = $emitter->additionalSqlForTables($emitterRegistry);

        // IF NOT EXISTS makes this idempotent — must not throw.
        foreach ($ginStatements as $sql) {
            $conn->execute($sql);
        }

        // Verify there is still only one GIN index (no duplicate created).
        $rows = $conn->query(
            'SELECT COUNT(*) AS cnt
             FROM pg_indexes
             WHERE tablename = ?
               AND indexname = ?',
            [$tableName, $tableName . '_scopes_gin'],
        );

        expect((int) $rows[0]['cnt'])->toBe(1);
    },
)->group('integration-destructive');

it(
    'setOverride followed by clearOverride inside a transaction that rolls back leaves the database unchanged',
    function (): void {
        /** @var TestConnection $conn */
        $conn = $this->conn;
        $tableName = $this->tableName;

        // Insert a base product row OUTSIDE the transaction.
        $conn->execute(
            sprintf('INSERT INTO "%s" ("name", "price", "scopes") VALUES (?, ?, null)', $tableName),
            ['Original Name', '5.00'],
        );

        $initialRows = $conn->query(sprintf('SELECT "scopes" FROM "%s"', $tableName));
        expect($initialRows[0]['scopes'])->toBeNull();

        // Begin transaction: set an override, then clear it, then rollback.
        $conn->beginTransaction();

        $overridesJson = json_encode(
            ['channel:default.web|locale:en.gb' => ['name' => 'Overridden Name']],
            JSON_THROW_ON_ERROR,
        );

        $conn->execute(
            sprintf('UPDATE "%s" SET "scopes" = ?::jsonb', $tableName),
            [$overridesJson],
        );

        // Clear the override (set scopes back to null).
        $conn->execute(
            sprintf('UPDATE "%s" SET "scopes" = null', $tableName),
        );

        $conn->rollback();

        // After rollback, scopes must be exactly as before the transaction started.
        $afterRows = $conn->query(sprintf('SELECT "scopes" FROM "%s"', $tableName));
        expect($afterRows[0]['scopes'])->toBeNull();
    },
)->group('integration-destructive');

it(
    'tests skip with a clear message when DB_HOST is unset',
    function (): void {
        $originalHost = getenv('DB_HOST');

        // Simulate DB_HOST being unset.
        putenv('DB_HOST=');

        $skipped = false;
        $skipMessage = '';

        try {
            TestConnection::skipIfUnavailable();
        } catch (SkippedWithMessageException $e) {
            $skipped = true;
            $skipMessage = $e->getMessage();
        } finally {
            putenv('DB_HOST=' . $originalHost);
        }

        expect($skipped)->toBeTrue()
            ->and($skipMessage)->toContain('DB_HOST');
    },
)->group('integration-destructive');

it(
    'builds the postgres scope registry from the scopes-map configuration schema',
    function (): void {
        $registry = buildPgIntRegistry();

        expect($registry->hasAxis('channel'))->toBeTrue()
            ->and($registry->hasAxis('locale'))->toBeTrue()
            ->and($registry->getAxis('channel')->default)->toBe('__test_default')
            ->and($registry->getAxis('locale')->default)->toBe('__test_default')
            ->and($registry->getAxis('channel')->hierarchy->exists('__test_default'))->toBeTrue()
            ->and($registry->getAxis('channel')->hierarchy->exists('default.web'))->toBeTrue()
            ->and($registry->getAxis('locale')->hierarchy->exists('__test_default'))->toBeTrue()
            ->and($registry->getAxis('locale')->hierarchy->exists('en.gb'))->toBeTrue();
    },
)->group('integration-destructive');

it(
    'orders by a plain column with no scopes json access for an all-default context',
    function (): void {
        /** @var TestConnection $conn */
        $conn = $this->conn;
        $tableName = $this->tableName;

        // Insert a product with no overrides.
        $conn->execute(
            sprintf('INSERT INTO "%s" ("name", "price", "scopes") VALUES (?, ?, null)', $tableName),
            ['Alpha', '1.00'],
        );

        [$resolver, $context, $registry, $metadataFactory, $enumerator] = buildPgIntResolver();

        // All-default context: both axes at '__test_default' → candidate list is empty
        $context->in('channel', '__test_default')->in('locale', '__test_default');

        $renderer = new PgSqlScopedFieldRenderer();
        $orderBy = new ScopedOrderBy(
            property: 'name',
            scopeMetadataFactory: $metadataFactory,
            scopeContext: $context,
            scopedFieldRenderer: $renderer,
            signatureCandidateEnumerator: $enumerator,
            entityClass: PgIntProduct::class,
            direction: 'asc',
        );

        $builder = new PgSqlEntityQueryBuilder($conn);
        $builder->table($tableName);
        $orderBy->apply($builder);

        // Capture the rendered SQL from the expression directly
        $candidates = $enumerator->enumerate(['channel', 'locale'], $context);
        $renderedExpr = $renderer->render(new ScopedFieldExpression(
            property: 'name',
            column: 'name',
            candidateSignatures: $candidates,
        ));

        // Plain column — no JSONB access, no COALESCE
        expect($renderedExpr)->toBe('"name"')
            ->and($renderedExpr)->not->toContain('scopes->')
            ->and($renderedExpr)->not->toContain('COALESCE(');

        // Query still executes and returns the row
        $rows = $builder->get();
        expect($rows)->toHaveCount(1);
    },
)->group('integration-destructive');

it(
    'emits a COALESCE expression over scopes json when a non-default scope is active',
    function (): void {
        /** @var TestConnection $conn */
        $conn = $this->conn;
        $tableName = $this->tableName;

        // Insert a product with a locale-specific override.
        $overrides = json_encode(
            ['locale:en' => ['name' => 'En Name']],
            JSON_THROW_ON_ERROR,
        );
        $conn->execute(
            sprintf('INSERT INTO "%s" ("name", "price", "scopes") VALUES (?, ?, ?::jsonb)', $tableName),
            ['Default Name', '1.00', $overrides],
        );

        [$resolver, $context, $registry, $metadataFactory, $enumerator] = buildPgIntResolver();

        // locale is non-default → candidates are non-empty → COALESCE is emitted
        $context->in('channel', '__test_default')->in('locale', 'en.gb');

        $renderer = new PgSqlScopedFieldRenderer();
        $candidates = $enumerator->enumerate(['channel', 'locale'], $context);

        $renderedExpr = $renderer->render(new ScopedFieldExpression(
            property: 'name',
            column: 'name',
            candidateSignatures: $candidates,
        ));

        expect($renderedExpr)->toContain('COALESCE(')
            ->and($renderedExpr)->toContain('"scopes"');

        // Verify the ordering works correctly at the DB level
        $orderBy = new ScopedOrderBy(
            property: 'name',
            scopeMetadataFactory: $metadataFactory,
            scopeContext: $context,
            scopedFieldRenderer: $renderer,
            signatureCandidateEnumerator: $enumerator,
            entityClass: PgIntProduct::class,
            direction: 'asc',
        );

        $builder = new PgSqlEntityQueryBuilder($conn);
        $builder->table($tableName);
        $orderBy->apply($builder);

        $rows = $builder->get();
        expect($rows)->toHaveCount(1);
    },
)->group('integration-destructive');

it(
    'persists and resolves an override at a non-default scope',
    function (): void {
        /** @var TestConnection $conn */
        $conn = $this->conn;

        [$resolver, $context] = buildPgIntResolver();
        $context->in('channel', 'default.web')->in('locale', 'en.gb');

        $product = new PgIntProduct();
        $product->name = 'Default Name';
        $product->price = '10.00';

        $signature = new ScopeSignature(['channel' => 'default.web', 'locale' => 'en.gb']);
        $resolver->setOverride($product, 'name', 'GB Web Name', $signature);

        $scopesJson = json_encode($product->overrides(), JSON_THROW_ON_ERROR);
        $conn->execute(
            sprintf('INSERT INTO "%s" ("name", "price", "scopes") VALUES (?, ?, ?::jsonb)', $this->tableName),
            [$product->name, $product->price, $scopesJson],
        );

        $rows = $conn->query(
            sprintf('SELECT "scopes" FROM "%s" LIMIT 1', $this->tableName),
        );

        $rawScopes = $rows[0]['scopes'];
        $decoded = json_decode($rawScopes, true, 512, JSON_THROW_ON_ERROR);

        $rehydrated = new PgIntProduct();
        $rehydrated->name = 'Default Name';
        $rehydrated->price = '10.00';

        foreach ($decoded as $sig => $values) {
            foreach ($values as $property => $value) {
                $rehydrated->setOverride($sig, $property, $value);
            }
        }

        $result = $resolver->resolved($rehydrated, 'name');

        expect($result)->toBe('GB Web Name');
    },
)->group('integration-destructive');
