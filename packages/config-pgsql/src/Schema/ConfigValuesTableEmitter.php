<?php

declare(strict_types=1);

namespace Markommerce\Config\PgSql\Schema;

class ConfigValuesTableEmitter
{
    /**
     * Returns the raw SQL statements to create the config_values table and its GIN index.
     *
     * All statements use IF NOT EXISTS so re-running is idempotent.
     *
     * @return list<string>
     */
    public function createStatements(string $tableName = 'config_values'): array
    {
        return [
            $this->buildCreateTableSql($tableName),
            $this->buildGinIndexSql($tableName),
        ];
    }

    private function buildCreateTableSql(string $tableName): string
    {
        return sprintf(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS "%s" (
                config_key   VARCHAR(255) PRIMARY KEY,
                value        JSONB,
                overrides    JSONB        NOT NULL DEFAULT '{}'::jsonb,
                version      INTEGER      NOT NULL DEFAULT 0,
                updated_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW()
            )
            SQL,
            $tableName,
        );
    }

    private function buildGinIndexSql(string $tableName): string
    {
        return sprintf(
            'CREATE INDEX IF NOT EXISTS "%s_overrides_gin" ON "%s" USING GIN (overrides)',
            $tableName,
            $tableName,
        );
    }
}
