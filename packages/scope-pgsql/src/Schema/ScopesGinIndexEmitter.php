<?php

declare(strict_types=1);

namespace Markommerce\Scope\PgSql\Schema;

use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\Query\IdentifierValidator;
use Marko\Database\Schema\SchemaRegistry;
use Markommerce\Scope\Storage\HasScopes;

class ScopesGinIndexEmitter
{
    /**
     * Returns additional raw SQL statements (GIN index creation) for all tables
     * whose entity or any extender uses the HasScopes trait.
     *
     * @return list<string>
     *
     * @throws InvalidColumnException
     */
    public function additionalSqlForTables(SchemaRegistry $registry): array
    {
        $statements = [];

        foreach ($registry->getTableNames() as $tableName) {
            if (!IdentifierValidator::isValidIdentifier($tableName)) {
                throw InvalidColumnException::invalidColumn($tableName);
            }

            if ($this->tableHasScopes($registry, $tableName)) {
                $statements[] = $this->buildGinIndexSql($tableName);
            }
        }

        return $statements;
    }

    private function tableHasScopes(
        SchemaRegistry $registry,
        string $tableName,
    ): bool {
        $entityClass = $registry->getEntityClass($tableName);

        if ($entityClass === null) {
            return false;
        }

        if ($this->classUsesHasScopes($entityClass)) {
            return true;
        }

        $metadata = $registry->getMetadata($tableName);

        if ($metadata === null) {
            return false;
        }

        return array_any($metadata->extenders, fn (string $extender) => $this->classUsesHasScopes($extender));
    }

    /**
     * @param class-string $class
     */
    private function classUsesHasScopes(string $class): bool
    {
        $traits = class_uses($class);

        if ($traits === false) {
            return false;
        }

        return array_key_exists(HasScopes::class, $traits);
    }

    private function buildGinIndexSql(string $tableName): string
    {
        return sprintf(
            'CREATE INDEX IF NOT EXISTS "%s_scopes_gin" ON "%s" USING GIN ("scopes" jsonb_path_ops)',
            $tableName,
            $tableName,
        );
    }
}
