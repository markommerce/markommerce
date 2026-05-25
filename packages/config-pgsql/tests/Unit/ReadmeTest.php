<?php

declare(strict_types=1);

it('has a README.md at the package root', function (): void {
    expect(file_exists(__DIR__ . '/../../README.md'))->toBeTrue();
});

it('states the package provides the PgSQL storage driver for markommerce/config', function (): void {
    $content = file_get_contents(__DIR__ . '/../../README.md');
    expect($content)->toContain('PgSQL')
        ->and($content)->toContain('markommerce/config');
});

it('shows the installation steps including running the table migration', function (): void {
    $content = file_get_contents(__DIR__ . '/../../README.md');
    expect($content)->toContain('composer require markommerce/config-pgsql')
        ->and($content)->toContain('config_values')
        ->and($content)->toContain('db:migrate');
});

it(
    'documents required environment variables (DB connection + MARKOMMERCE_CONFIG_SECRET_KEY if secrets used)',
    function (): void {
        $content = file_get_contents(__DIR__ . '/../../README.md');
        expect($content)->toContain('DB_')
            ->and($content)->toContain('MARKOMMERCE_CONFIG_SECRET_KEY');
    }
);

it('links to the interface package README and the docs site for usage', function (): void {
    $content = file_get_contents(__DIR__ . '/../../README.md');
    expect($content)->toContain('markommerce/config')
        ->and($content)->toContain('markommerce.dev/docs/packages/config-pgsql');
});
