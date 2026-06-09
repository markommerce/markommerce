<?php

declare(strict_types=1);

namespace Markommerce\Testing\Database;

use Marko\Database\Connection\ConnectionInterface;
use Markommerce\Testing\Database\Exceptions\InvalidIdentifierException;

/**
 * Admin-level PostgreSQL connection for database lifecycle management
 * (CREATE DATABASE, DROP DATABASE, CREATE DATABASE ... TEMPLATE ...).
 *
 * Connects to a maintenance database (DB_ADMIN_DATABASE env var, defaulting
 * to 'postgres') so it can issue DDL commands that cannot run inside a
 * transaction. The underlying PgSqlConnection runs in PDO autocommit mode.
 */
class AdminConnection extends TestConnection
{
    /**
     * Pattern for safe, code-generated database identifiers.
     */
    private const string IDENTIFIER_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    public function __construct()
    {
        $adminDatabase = (string) (getenv('DB_ADMIN_DATABASE') ?: 'postgres');

        parent::__construct($adminDatabase);
    }

    /**
     * Create a database with the given name.
     *
     * @throws InvalidIdentifierException
     */
    public function createDatabase(string $name): void
    {
        $this->validateIdentifier($name);
        $this->execute("CREATE DATABASE $name");
    }

    /**
     * Drop a database if it exists, forcibly evicting any active sessions (PG13+).
     *
     * @throws InvalidIdentifierException
     */
    public function dropDatabase(string $name): void
    {
        $this->validateIdentifier($name);
        $this->execute("DROP DATABASE IF EXISTS $name WITH (FORCE)");
    }

    /**
     * Create a database from a template database.
     *
     * @throws InvalidIdentifierException
     */
    public function createDatabaseFromTemplate(string $name, string $template): void
    {
        $this->validateIdentifier($name);
        $this->validateIdentifier($template);
        $this->execute("CREATE DATABASE $name TEMPLATE $template");
    }

    /**
     * Build a ConnectionInterface bound to a given database name.
     *
     * @throws InvalidIdentifierException
     */
    public function connectionFor(string $database): ConnectionInterface
    {
        $this->validateIdentifier($database);

        return new TestConnection($database);
    }

    /**
     * @throws InvalidIdentifierException
     */
    private function validateIdentifier(string $name): void
    {
        if (preg_match(self::IDENTIFIER_PATTERN, $name) !== 1) {
            throw InvalidIdentifierException::forName($name);
        }
    }
}
