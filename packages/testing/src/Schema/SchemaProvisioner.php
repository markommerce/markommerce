<?php

declare(strict_types=1);

namespace Markommerce\Testing\Schema;

use Marko\Core\Discovery\ClassFileParser;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Entity\EntityDiscovery;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\SchemaBuilder;
use Marko\Database\PgSql\Sql\PgSqlGenerator;
use Marko\Database\Schema\SchemaRegistry;

class SchemaProvisioner
{
    private EntityDiscovery $entityDiscovery;

    private EntityMetadataFactory $metadataFactory;

    private SchemaBuilder $schemaBuilder;

    private PgSqlGenerator $generator;

    public function __construct()
    {
        $this->entityDiscovery = new EntityDiscovery(new ClassFileParser());
        $this->metadataFactory = new EntityMetadataFactory();
        $this->schemaBuilder = new SchemaBuilder();
        $this->generator = new PgSqlGenerator();
    }

    /**
     * Discover all table names across the given entity directories.
     *
     * @param array<string> $entityDirs
     * @return array<string>
     */
    public function tableNames(array $entityDirs): array
    {
        $registry = $this->buildRegistry($entityDirs);

        return $registry->getTableNames();
    }

    /**
     * Provision the schema (tables, indexes, foreign keys) into the given connection.
     *
     * @param array<string> $entityDirs
     */
    public function provision(
        ConnectionInterface $conn,
        array $entityDirs,
    ): void {
        $registry = $this->buildRegistry($entityDirs);
        $tables = $registry->getTables();

        // Step 1: Create all tables
        foreach ($tables as $table) {
            $conn->execute($this->generator->generateCreateTable($table));
        }

        // Step 2: Create all indexes
        foreach ($tables as $table) {
            foreach ($table->indexes as $index) {
                $conn->execute($this->generator->generateAddIndex($table->name, $index));
            }
        }

        // Step 3: Create all foreign keys
        foreach ($tables as $table) {
            foreach ($table->foreignKeys as $foreignKey) {
                $conn->execute($this->generator->generateAddForeignKey($table->name, $foreignKey));
            }
        }
    }

    /**
     * Discover entities across multiple directories, deduplicate, and register in a fresh SchemaRegistry.
     *
     * @param array<string> $entityDirs
     */
    private function buildRegistry(array $entityDirs): SchemaRegistry
    {
        $entityClasses = [];

        foreach ($entityDirs as $dir) {
            foreach ($this->entityDiscovery->discoverInPath($dir) as $entityClass) {
                $entityClasses[$entityClass] = true;
            }
        }

        $registry = new SchemaRegistry($this->metadataFactory, $this->schemaBuilder);
        $registry->registerEntities(array_keys($entityClasses));

        return $registry;
    }
}
