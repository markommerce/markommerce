<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Migration\Migration;

it('the migration adds a non-null position column defaulting to zero', function (): void {
    $migrationFile = __DIR__ . '/../../../../../playground/database/migrations/20260604120000_alter_catalog_product_category.php';

    expect(file_exists($migrationFile))->toBeTrue('Migration file does not exist: ' . $migrationFile);

    $migration = require $migrationFile;

    expect($migration)->toBeInstanceOf(Migration::class);

    $upSql = [];
    $downSql = [];

    $capturingUp = new class ($upSql) implements ConnectionInterface
    {
        public function __construct(private array &$log) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(
            string $sql,
            array $bindings = [],
        ): array
        {
            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int
        {
            $this->log[] = $sql;

            return 1;
        }

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 0;
        }
    };

    $capturingDown = new class ($downSql) implements ConnectionInterface
    {
        public function __construct(private array &$log) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(
            string $sql,
            array $bindings = [],
        ): array
        {
            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int
        {
            $this->log[] = $sql;

            return 1;
        }

        public function prepare(string $sql): StatementInterface
        {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 0;
        }
    };

    $migration->up($capturingUp);
    $migration->down($capturingDown);

    $upCombined = implode(' ', $upSql);
    $downCombined = implode(' ', $downSql);

    expect($upCombined)->toContain('catalog_product_category')
        ->and($upCombined)->toContain('ADD COLUMN')
        ->and($upCombined)->toContain('"position"')
        ->and($upCombined)->toContain('INTEGER')
        ->and($upCombined)->toContain('NOT NULL')
        ->and($upCombined)->toContain('DEFAULT 0');

    expect($downCombined)->toContain('catalog_product_category')
        ->and($downCombined)->toContain('DROP COLUMN')
        ->and($downCombined)->toContain('"position"');
});
