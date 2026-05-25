<?php

declare(strict_types=1);

namespace Markommerce\Config\PgSql;

use DateTimeImmutable;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;
use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\ValueObjects\ConfigRow;
use PDOException;

class PgsqlConfigStorage implements ConfigStorageInterface
{
    public function __construct(
        private readonly ConnectionInterface&TransactionInterface $connection,
        private readonly string $tableName = 'config_values',
    ) {}

    /**
     * @throws PDOException
     */
    public function load(string $key): ?ConfigRow
    {
        $rows = $this->connection->query(
            sprintf(
                'SELECT config_key, value, overrides, version, updated_at FROM "%s" WHERE config_key = ?',
                $this->tableName,
            ),
            [$key],
        );

        if ($rows === []) {
            return null;
        }

        return $this->hydrateRow($rows[0]);
    }

    /**
     * @param list<string> $keys
     * @return array<string, ConfigRow>
     * @throws PDOException
     */
    public function loadMany(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($keys), '?'));

        $rows = $this->connection->query(
            sprintf(
                'SELECT config_key, value, overrides, version, updated_at FROM "%s" WHERE config_key IN (%s)',
                $this->tableName,
                $placeholders,
            ),
            $keys,
        );

        $result = [];

        foreach ($rows as $row) {
            $configRow = $this->hydrateRow($row);
            $result[$configRow->key] = $configRow;
        }

        return $result;
    }

    /**
     * @throws PDOException
     */
    public function compareAndSave(
        string $key,
        ConfigRow $row,
        int $expectedVersion,
    ): bool
    {
        $isEmpty = $row->value === null && $row->overrides === [];

        if ($isEmpty) {
            return $this->handleEmptyRow($key, $expectedVersion);
        }

        if ($expectedVersion === 0) {
            return $this->handleInsert($key, $row);
        }

        return $this->handleUpdate($key, $row, $expectedVersion);
    }

    /**
     * @throws PDOException
     */
    private function handleEmptyRow(
        string $key,
        int $expectedVersion,
    ): bool
    {
        $affected = $this->connection->execute(
            sprintf(
                'DELETE FROM "%s" WHERE config_key = ? AND version = ?',
                $this->tableName,
            ),
            [$key, $expectedVersion],
        );

        if ($affected === 1) {
            return true;
        }

        // DELETE affected 0 rows
        if ($expectedVersion === 0) {
            // Check if row exists at all
            $rows = $this->connection->query(
                sprintf(
                    'SELECT 1 FROM "%s" WHERE config_key = ?',
                    $this->tableName,
                ),
                [$key],
            );

            // If no row exists, it's a no-op — return true
            // If a row exists with a different version, return false
            return $rows === [];
        }

        // expectedVersion > 0 and DELETE failed — version mismatch
        return false;
    }

    /**
     * @throws PDOException
     */
    private function handleInsert(
        string $key,
        ConfigRow $row,
    ): bool
    {
        $valueJson = $row->value !== null ? json_encode($row->value) : null;
        $overridesJson = json_encode($row->overrides);

        $rows = $this->connection->query(
            sprintf(
                <<<'SQL'
                INSERT INTO "%s" (config_key, value, overrides, version, updated_at)
                VALUES (?, ?::jsonb, ?::jsonb, 1, NOW())
                ON CONFLICT (config_key) DO UPDATE
                    SET value      = EXCLUDED.value,
                        overrides  = EXCLUDED.overrides,
                        version    = "%s".version + 1,
                        updated_at = NOW()
                    WHERE "%s".version = 0
                RETURNING version
                SQL,
                $this->tableName,
                $this->tableName,
                $this->tableName,
            ),
            [$key, $valueJson, $overridesJson],
        );

        return $rows !== [];
    }

    /**
     * @throws PDOException
     */
    private function handleUpdate(
        string $key,
        ConfigRow $row,
        int $expectedVersion,
    ): bool
    {
        $valueJson = $row->value !== null ? json_encode($row->value) : null;
        $overridesJson = json_encode($row->overrides);

        $rows = $this->connection->query(
            sprintf(
                <<<'SQL'
                UPDATE "%s"
                SET value      = ?::jsonb,
                    overrides  = ?::jsonb,
                    version    = version + 1,
                    updated_at = NOW()
                WHERE config_key = ?
                  AND version    = ?
                RETURNING version
                SQL,
                $this->tableName,
            ),
            [$valueJson, $overridesJson, $key, $expectedVersion],
        );

        return $rows !== [];
    }

    /**
     * @param array<string, mixed> $dbRow
     */
    private function hydrateRow(array $dbRow): ConfigRow
    {
        return new ConfigRow(
            key: (string) $dbRow['config_key'],
            value: isset($dbRow['value']) ? json_decode((string) $dbRow['value'], true) : null,
            overrides: json_decode((string) $dbRow['overrides'], true) ?? [],
            version: (int) $dbRow['version'],
            updatedAt: isset($dbRow['updated_at']) ? new DateTimeImmutable((string) $dbRow['updated_at']) : null,
        );
    }
}
