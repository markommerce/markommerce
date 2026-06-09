<?php

declare(strict_types=1);

namespace Markommerce\Testing\Database;

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\PgSql\Connection\PgSqlConnection;
use PDO;

/**
 * Reusable test-only PgSqlConnection subclass that reads connection parameters
 * from DB_* environment variables rather than a config file.
 *
 * Accepts an optional target database name so per-worker cloned databases can
 * be connected to independently of the DB_DATABASE env var.
 */
class TestConnection extends PgSqlConnection
{
    /**
     * @var array<string> Required env vars for the connection.
     */
    private const array REQUIRED_ENV_VARS = [
        'DB_HOST',
        'DB_PORT',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
    ];

    private string $envHost;

    private int $envPort;

    private string $targetDatabase;

    private string $envUsername;

    private string $envPassword;

    /**
     * @param string|null $database Target database name; defaults to DB_DATABASE env var.
     */
    public function __construct(?string $database = null)
    {
        $this->envHost = (string) getenv('DB_HOST');
        $this->envPort = (int) getenv('DB_PORT');
        $this->targetDatabase = $database ?? (string) getenv('DB_DATABASE');
        $this->envUsername = (string) getenv('DB_USERNAME');
        $this->envPassword = (string) getenv('DB_PASSWORD');

        parent::__construct(
            config: self::buildDummyConfig($this->targetDatabase),
        );
    }

    /**
     * Skip the current test with a clear message when any required env var is missing.
     */
    public static function skipIfUnavailable(): void
    {
        $missing = [];

        foreach (self::REQUIRED_ENV_VARS as $var) {
            $value = getenv($var);

            if ($value === false || $value === '') {
                $missing[] = $var;
            }
        }

        if ($missing !== []) {
            // @phpstan-ignore method.notFound (test() proxy is always available in Pest context; this class is test-only)
            test()->markTestSkipped(
                'Integration test requires env vars: ' . implode(', ', $missing) . '. '
                . 'Set them to run this test against a real Postgres instance.',
            );
        }
    }

    protected function createPdo(
        string $dsn,
        string $username,
        string $password,
        array $options,
    ): PDO {
        $testDsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $this->envHost,
            $this->envPort,
            $this->targetDatabase,
        );

        return new PDO(
            $testDsn,
            $this->envUsername,
            $this->envPassword,
            $options,
        );
    }

    private static function buildDummyConfig(string $database): DatabaseConfig
    {
        $host = (string) (getenv('DB_HOST') ?: 'localhost');
        $port = (int) (getenv('DB_PORT') ?: 5432);
        $username = (string) (getenv('DB_USERNAME') ?: 'postgres');
        $password = (string) (getenv('DB_PASSWORD') ?: '');

        $tmpDir = sys_get_temp_dir() . '/marko-test-db-config-testing-' . getmypid();

        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $configDir = $tmpDir . '/config';

        if (!is_dir($configDir)) {
            mkdir($configDir, 0777, true);
        }

        file_put_contents($configDir . '/database.php', sprintf(
            '<?php return %s;',
            var_export([
                'driver' => 'pgsql',
                'host' => $host,
                'port' => $port,
                'database' => $database,
                'username' => $username,
                'password' => $password,
            ], true),
        ));

        return new DatabaseConfig(new ProjectPaths($tmpDir));
    }
}
