<?php

declare(strict_types=1);

namespace Markommerce\ConfigScope\PgSql;

use Marko\Database\Connection\ConnectionInterface;
use Markommerce\ConfigScope\Contracts\ScopedConfigStorageInterface;
use PDOException;

class PgsqlScopedConfigStorage implements ScopedConfigStorageInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $tableName = 'config_value_overrides',
    ) {}

    /**
     * @return array<string, mixed>
     * @throws PDOException
     */
    public function loadOverrides(string $key): array
    {
        $rows = $this->connection->query(
            sprintf(
                'SELECT signature, value FROM "%s" WHERE config_key = ?',
                $this->tableName,
            ),
            [$key],
        );

        $result = [];

        foreach ($rows as $row) {
            $result[(string) $row['signature']] = json_decode((string) $row['value'], true);
        }

        return $result;
    }

    /**
     * @param list<string> $keys
     * @return array<string, array<string, mixed>>
     * @throws PDOException
     */
    public function loadManyOverrides(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($keys), '?'));

        $rows = $this->connection->query(
            sprintf(
                'SELECT config_key, signature, value FROM "%s" WHERE config_key IN (%s)',
                $this->tableName,
                $placeholders,
            ),
            $keys,
        );

        $result = [];

        foreach ($rows as $row) {
            $configKey = (string) $row['config_key'];
            $signature = (string) $row['signature'];

            if (!isset($result[$configKey])) {
                $result[$configKey] = [];
            }

            $result[$configKey][$signature] = json_decode((string) $row['value'], true);
        }

        return $result;
    }

    /**
     * @throws PDOException
     */
    public function saveOverride(
        string $key,
        string $signature,
        mixed $value,
    ): void
    {
        $valueJson = json_encode($value);

        $this->connection->execute(
            sprintf(
                <<<'SQL'
                INSERT INTO "%s" (config_key, signature, value, version, updated_at)
                VALUES (?, ?, ?::jsonb, 0, NOW())
                ON CONFLICT (config_key, signature) DO UPDATE
                    SET value      = EXCLUDED.value,
                        version    = "%s".version + 1,
                        updated_at = NOW()
                SQL,
                $this->tableName,
                $this->tableName,
            ),
            [$key, $signature, $valueJson],
        );
    }

    /**
     * @throws PDOException
     */
    public function deleteOverride(
        string $key,
        string $signature,
    ): void
    {
        $this->connection->execute(
            sprintf(
                'DELETE FROM "%s" WHERE config_key = ? AND signature = ?',
                $this->tableName,
            ),
            [$key, $signature],
        );
    }
}
