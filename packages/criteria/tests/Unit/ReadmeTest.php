<?php

declare(strict_types=1);

$readmePath = dirname(__DIR__, 2) . '/README.md';
$docsPath = dirname(__DIR__, 4) . '/docs/src/content/docs/packages/criteria.md';

// Per the project's DOCS-STANDARDS, package READMEs stay slim (title, one-liner,
// install, quick example, docs link); the comprehensive API reference lives on the
// docs site at docs/src/content/docs/packages/criteria.md. These tests verify the
// slim README essentials and that the docs page documents the full public API.

it(
    'the package README exists and documents the strategy and counter contracts',
    function () use ($readmePath, $docsPath): void {
        expect(file_exists($readmePath))->toBeTrue();
        expect(file_exists($docsPath))->toBeTrue();
    
        $readme = file_get_contents($readmePath);
        expect($readme)
            ->toContain('# markommerce/criteria')
            ->toContain('PaginationStrategyInterface')
            ->toContain('https://markommerce.dev/docs/packages/criteria');
    
        $docs = file_get_contents($docsPath);
        expect($docs)
            ->toContain('PaginationStrategyInterface')
            ->toContain('RowCounterInterface')
            ->toContain('ExactRowCounter')
            ->toContain('EstimatedRowCounter')
            ->toContain('KeysetPaginationStrategy')
            ->toContain('OffsetPaginationStrategy');
    }
);

it('the README documents the offset vs keyset random-access distinction', function () use ($docsPath): void {
    $docs = file_get_contents($docsPath);

    expect($docs)
        ->toContain('RandomAccessPageInterface')
        ->toContain('OffsetPage')
        ->toContain('positionForPage');
});

it('the README documents position-token opacity and versioning', function () use ($docsPath): void {
    $docs = file_get_contents($docsPath);

    expect($docs)
        ->toContain('PositionCodec')
        ->toContain('base64url')
        ->toContain('version');
});

it('the README includes a consumer integration example', function () use ($readmePath, $docsPath): void {
    $readme = file_get_contents($readmePath);
    expect($readme)
        ->toContain('```php')
        ->toContain('PageRequest')
        ->toContain('paginate(');

    $docs = file_get_contents($docsPath);
    expect($docs)
        ->toContain('```php')
        ->toContain('paginate(');
});
