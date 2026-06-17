<?php

declare(strict_types=1);

use Markommerce\Indexer\AbstractIndexer;

it('autoloads a class from the Markommerce\Indexer namespace', function (): void {
    expect(class_exists(AbstractIndexer::class))->toBeTrue();
});

it('marks indexer as a marko module in composer extra', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['extra']['marko']['module'])->toBeTrue();
});

it('documents the shared indexer core purpose and lifecycle in its README', function (): void {
    $readme = (string) file_get_contents(dirname(__DIR__) . '/README.md');

    expect($readme)
        ->toContain('markommerce/indexer')
        ->toContain('IndexerInterface')
        ->toContain('AbstractIndexer')
        ->toContain('ScopePassRunner')
        ->toContain('index:rebuild')
        ->toContain('## Installation')
        ->toContain('## Quick Example')
        ->toContain('## Documentation');
});
