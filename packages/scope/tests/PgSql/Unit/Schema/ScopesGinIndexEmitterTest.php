<?php

declare(strict_types=1);

namespace Markommerce\Scope\PgSql\Tests\Unit\Schema;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\Schema\SchemaRegistry;
use Markommerce\Scope\PgSql\Schema\ScopesGinIndexEmitter;
use Markommerce\Scope\Storage\HasScopes;
use Markommerce\Scope\Storage\HasScopesInterface;

// Test fixtures

#[Table('items')]
class ItemWithScopes extends Entity implements HasScopesInterface
{
    use HasScopes;

    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    /** @noinspection PhpUnused */
    #[Column(length: 255)]
    public string $name;
}

#[Table('widgets')]
class WidgetWithoutScopes extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;
}

#[Table('categories')]
class CategoryParent extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    /** @noinspection PhpUnused */
    #[Column(length: 255)]
    public string $title;
}

#[Table(extends: CategoryParent::class)]
class CategoryScopedExtender extends Entity implements HasScopesInterface
{
    use HasScopes;
}

#[Table('orders')]
class OrderParent extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;
}

#[Table(extends: OrderParent::class)]
class OrderExtenderNoScopes extends Entity
{
    /** @noinspection PhpUnused */
    #[Column(nullable: true)]
    public ?string $notes = null;
}

#[Table('invoices')]
class InvoiceWithScopes extends Entity implements HasScopesInterface
{
    use HasScopes;

    /** @noinspection PhpUnused */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;
}

// Tests

it(
    'the emitter returns a CREATE INDEX IF NOT EXISTS statement for a table whose entity uses HasScopes',
    function (): void {
        $registry = new SchemaRegistry(
            metadataFactory: new EntityMetadataFactory(),
            schemaBuilder: new SchemaBuilder(),
        );
        $registry->registerEntity(ItemWithScopes::class);

        $emitter = new ScopesGinIndexEmitter();
        $statements = $emitter->additionalSqlForTables($registry);

        expect($statements)->toHaveCount(1)
            ->and($statements[0])->toContain('CREATE INDEX IF NOT EXISTS');
    },
);

it(
    'the emitter returns zero statements for tables whose entity (and extenders) do not use HasScopes',
    function (): void {
        $registry = new SchemaRegistry(
            metadataFactory: new EntityMetadataFactory(),
            schemaBuilder: new SchemaBuilder(),
        );
        $registry->registerEntities([OrderParent::class, OrderExtenderNoScopes::class]);

        $emitter = new ScopesGinIndexEmitter();
        $statements = $emitter->additionalSqlForTables($registry);

        expect($statements)->toBeEmpty();
    },
);

it(
    'the emitter returns a CREATE INDEX IF NOT EXISTS statement for a table whose extender uses HasScopes (parent does not)',
    function (): void {
        $registry = new SchemaRegistry(
            metadataFactory: new EntityMetadataFactory(),
            schemaBuilder: new SchemaBuilder(),
        );
        $registry->registerEntities([CategoryParent::class, CategoryScopedExtender::class]);

        $emitter = new ScopesGinIndexEmitter();
        $statements = $emitter->additionalSqlForTables($registry);

        expect($statements)->toHaveCount(1)
            ->and($statements[0])->toContain('CREATE INDEX IF NOT EXISTS');
    },
);

it('the emitted statement uses the jsonb_path_ops operator class explicitly', function (): void {
    $registry = new SchemaRegistry(
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
    );
    $registry->registerEntity(ItemWithScopes::class);

    $emitter = new ScopesGinIndexEmitter();
    $statements = $emitter->additionalSqlForTables($registry);

    expect($statements[0])->toContain('jsonb_path_ops');
});

it('the emitted index name is "<table>_scopes_gin"', function (): void {
    $registry = new SchemaRegistry(
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
    );
    $registry->registerEntity(ItemWithScopes::class);

    $emitter = new ScopesGinIndexEmitter();
    $statements = $emitter->additionalSqlForTables($registry);

    expect($statements[0])->toContain('"items_scopes_gin"');
});

it('the emitted statement uses CREATE INDEX IF NOT EXISTS (no separate idempotency machinery)', function (): void {
    $registry = new SchemaRegistry(
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
    );
    $registry->registerEntity(ItemWithScopes::class);

    $emitter = new ScopesGinIndexEmitter();
    $statements = $emitter->additionalSqlForTables($registry);

    expect($statements[0])->toMatch('/^CREATE INDEX IF NOT EXISTS /');
});

it('the emitted statement quotes the table name and column name with double quotes', function (): void {
    $registry = new SchemaRegistry(
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
    );
    $registry->registerEntity(ItemWithScopes::class);

    $emitter = new ScopesGinIndexEmitter();
    $statements = $emitter->additionalSqlForTables($registry);

    expect($statements[0])
        ->toContain('ON "items"')
        ->toContain('"scopes"');
});

it('the emitter throws InvalidColumnException when the table name fails IdentifierValidator', function (): void {
    // Build a fake registry that returns an invalid table name
    $registry = new class (
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
    ) extends SchemaRegistry
    {
        public function getTableNames(): array
        {
            return ['invalid-table-name!'];
        }
    };

    $emitter = new ScopesGinIndexEmitter();

    expect(fn () => $emitter->additionalSqlForTables($registry))
        ->toThrow(InvalidColumnException::class);
});

it('the emitter returns one statement per HasScopes-bearing table when multiple are registered', function (): void {
    $registry = new SchemaRegistry(
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
    );
    // ItemWithScopes uses HasScopes directly, InvoiceWithScopes uses it directly too
    // WidgetWithoutScopes does not
    $registry->registerEntity(ItemWithScopes::class);
    $registry->registerEntity(WidgetWithoutScopes::class);
    $registry->registerEntity(InvoiceWithScopes::class);

    $emitter = new ScopesGinIndexEmitter();
    $statements = $emitter->additionalSqlForTables($registry);

    expect($statements)->toHaveCount(2);

    $combined = implode(' ', $statements);
    expect($combined)
        ->toContain('"items_scopes_gin"')
        ->toContain('"invoices_scopes_gin"')
        ->not->toContain('"widgets_scopes_gin"');
});

it('the emitter does NOT add a Schema\\Index object to the registry (it only emits raw SQL)', function (): void {
    $registry = new SchemaRegistry(
        metadataFactory: new EntityMetadataFactory(),
        schemaBuilder: new SchemaBuilder(),
    );
    $registry->registerEntity(ItemWithScopes::class);

    $indexCountBefore = count($registry->getTable('items')->indexes);

    $emitter = new ScopesGinIndexEmitter();
    $emitter->additionalSqlForTables($registry);

    $indexCountAfter = count($registry->getTable('items')->indexes);

    expect($indexCountAfter)->toBe($indexCountBefore);
});
