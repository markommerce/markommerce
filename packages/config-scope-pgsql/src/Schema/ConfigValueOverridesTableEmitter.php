<?php

declare(strict_types=1);

namespace Markommerce\ConfigScope\PgSql\Schema;

class ConfigValueOverridesTableEmitter
{
    /**
     * Returns the raw SQL statements to create the config_value_overrides table.
     *
     * All statements use IF NOT EXISTS so re-running is idempotent.
     *
     * @return list<string>
     */
    public function createStatements(string $tableName = 'config_value_overrides'): array
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
                config_key VARCHAR(255) NOT NULL,
                signature  VARCHAR(255) NOT NULL,
                value      JSONB        NOT NULL,
                version    INTEGER      NOT NULL DEFAULT 0,
                updated_at TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
                PRIMARY KEY (config_key, signature)
            )
            SQL,
            $tableName,
        );
    }
}
