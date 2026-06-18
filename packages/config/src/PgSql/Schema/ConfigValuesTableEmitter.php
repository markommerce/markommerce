<?php

declare(strict_types=1);

namespace Markommerce\Config\PgSql\Schema;

class ConfigValuesTableEmitter
{
    /**
     * Returns the raw SQL statements to create the config_values table.
     *
     * All statements use IF NOT EXISTS so re-running is idempotent.
     *
     * @return list<string>
     */
    public function createStatements(string $tableName = 'config_values'): array
    {
        return [
            $this->buildCreateTableSql($tableName),
        ];
    }

    private function buildCreateTableSql(string $tableName): string
    {
        return sprintf(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS "%s" (
                config_key   VARCHAR(255) PRIMARY KEY,
                value        JSONB,
                version      INTEGER      NOT NULL DEFAULT 0,
                updated_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW()
            )
            SQL,
            $tableName,
        );
    }
}
