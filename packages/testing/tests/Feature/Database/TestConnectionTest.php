<?php

declare(strict_types=1);

namespace Markommerce\Testing\Tests\Feature\Database;

use Markommerce\Testing\Database\AdminConnection;
use Markommerce\Testing\Database\TestConnection;
use Marko\Database\Connection\ConnectionInterface;

it('connects to the database named in DB env and runs a trivial query', function (): void {
    TestConnection::skipIfUnavailable();

    $conn = new TestConnection();
    $result = $conn->query('SELECT 1 AS value');

    expect($result)->toHaveCount(1)
        ->and((int) $result[0]['value'])->toBe(1);
})->group('integration-destructive');

it('connects to an explicitly provided database name', function (): void {
    TestConnection::skipIfUnavailable();

    $dbName = (string) getenv('DB_DATABASE');
    $conn = new TestConnection($dbName);
    $result = $conn->query('SELECT current_database() AS db');

    expect($result)->toHaveCount(1)
        ->and($result[0]['db'])->toBe($dbName);
})->group('integration-destructive');

it('creates and drops a database via the admin connection', function (): void {
    TestConnection::skipIfUnavailable();

    $admin = new AdminConnection();
    $dbName = 'marko_test_admin_connection_' . getmypid();

    $admin->createDatabase($dbName);

    $result = $admin->query(
        'SELECT datname FROM pg_database WHERE datname = :name',
        ['name' => $dbName],
    );
    expect($result)->toHaveCount(1);

    $admin->dropDatabase($dbName);

    $result = $admin->query(
        'SELECT datname FROM pg_database WHERE datname = :name',
        ['name' => $dbName],
    );
    expect($result)->toHaveCount(0);
})->group('integration-destructive');

it('creates a database from a template database', function (): void {
    TestConnection::skipIfUnavailable();

    $admin = new AdminConnection();
    $templateName = 'marko_test_template_' . getmypid();
    $clonedName = 'marko_test_clone_' . getmypid();

    $admin->createDatabase($templateName);

    try {
        $admin->createDatabaseFromTemplate($clonedName, $templateName);

        $result = $admin->query(
            'SELECT datname FROM pg_database WHERE datname = :name',
            ['name' => $clonedName],
        );
        expect($result)->toHaveCount(1);
    } finally {
        $admin->dropDatabase($clonedName);
        $admin->dropDatabase($templateName);
    }
})->group('integration-destructive');

it('exposes a ConnectionInterface bound to a given database name', function (): void {
    TestConnection::skipIfUnavailable();

    $admin = new AdminConnection();
    $dbName = (string) getenv('DB_DATABASE');

    $conn = $admin->connectionFor($dbName);

    expect($conn)->toBeInstanceOf(ConnectionInterface::class);

    $result = $conn->query('SELECT current_database() AS db');
    expect($result[0]['db'])->toBe($dbName);
})->group('integration-destructive');

it('rejects an unsafe database identifier', function (): void {
    $admin = new AdminConnection();

    expect(fn () => $admin->createDatabase('invalid-name'))->toThrow(
        \Markommerce\Testing\Database\Exceptions\InvalidIdentifierException::class,
    );

    expect(fn () => $admin->dropDatabase('../../etc/passwd'))->toThrow(
        \Markommerce\Testing\Database\Exceptions\InvalidIdentifierException::class,
    );

    expect(fn () => $admin->createDatabaseFromTemplate('valid_name', 'bad name with spaces'))->toThrow(
        \Markommerce\Testing\Database\Exceptions\InvalidIdentifierException::class,
    );

    expect(fn () => $admin->connectionFor('1invalid'))->toThrow(
        \Markommerce\Testing\Database\Exceptions\InvalidIdentifierException::class,
    );
});

it('skips when required database env vars are absent', function (): void {
    $original = [];
    $vars = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];

    foreach ($vars as $var) {
        $original[$var] = getenv($var);
        putenv($var);
    }

    try {
        expect(fn () => TestConnection::skipIfUnavailable())
            ->toThrow(\PHPUnit\Framework\SkippedWithMessageException::class, 'DB_HOST');
    } finally {
        foreach ($original as $var => $value) {
            if ($value !== false) {
                putenv("$var=$value");
            }
        }
    }
});
