<?php

declare(strict_types=1);

it(
    'has a README.md with title # markommerce/scope and a single driver install line for markommerce/scope-pgsql',
    function (): void {
        $readmePath = dirname(__DIR__, 2) . '/README.md';
        $content = file_get_contents($readmePath);

        expect($content)->toContain('# markommerce/scope')
            ->and($content)->toContain('markommerce/scope-pgsql');
    },
);

it('has no reference to scope-mysql in the README', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->not->toContain('scope-mysql');
});

it('has a Documentation section in the README linking to the markommerce docs site', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('## Documentation');
});
