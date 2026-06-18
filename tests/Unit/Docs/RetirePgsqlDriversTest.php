<?php

declare(strict_types=1);

it('lists the correct package count with no -pgsql rows in the FEATURES inventory', function (): void {
    $content = (string) file_get_contents(__DIR__ . '/../../../FEATURES.md');

    expect($content)->toContain('37 packages')
        ->and($content)->not->toContain('scope-pgsql')
        ->and($content)->not->toContain('config-pgsql')
        ->and($content)->not->toContain('config-scope-pgsql')
        ->and($content)->not->toContain('attribute-pgsql');
});

it('documents that markommerce assumes Postgres and ships no per-domain DB driver packages', function (): void {
    $content = (string) file_get_contents(__DIR__ . '/../../../FEATURES.md');

    expect($content)->toContain('marko/database-pgsql')
        ->and($content)->toContain('no per-domain DB driver');
});

it('updates the scope README to describe an in-package Postgres implementation', function (): void {
    $content = (string) file_get_contents(__DIR__ . '/../../../packages/scope/README.md');

    expect($content)->toContain('ships its PostgreSQL implementation directly')
        ->and($content)->not->toContain('markommerce/scope-pgsql');
});
