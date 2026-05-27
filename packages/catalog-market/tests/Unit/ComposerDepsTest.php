<?php

declare(strict_types=1);

it('requires marko/core, marko/database, markommerce/catalog, markommerce/catalog-scope, and markommerce/market in catalog-market composer.json', function (): void {
    $composerPath = dirname(__DIR__, 2) . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);

    expect($composer['require'])->toHaveKey('marko/core')
        ->and($composer['require'])->toHaveKey('marko/database')
        ->and($composer['require'])->toHaveKey('markommerce/catalog')
        ->and($composer['require'])->toHaveKey('markommerce/catalog-scope')
        ->and($composer['require'])->toHaveKey('markommerce/market');
});
