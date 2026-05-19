<?php

declare(strict_types=1);

it(
    'has a README.md with title # markommerce/scope-pgsql and a markommerce/scope-pgsql install command',
    function (): void {
        $readmePath = dirname(__DIR__, 2) . '/README.md';
        $content = file_get_contents($readmePath);

        expect($content)->toContain('# markommerce/scope-pgsql')
            ->and($content)->toContain('composer require markommerce/scope-pgsql');
    },
);

it('mentions that markommerce/scope is installed as a transitive dep', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('markommerce/scope');
});

it('has a quick example showing scoped ORDER BY', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('ScopedOrderByFactory')
        ->and($content)->toContain('matching');
});

it('has a Documentation section', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('## Documentation');
});
