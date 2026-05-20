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

it('the README mentions the auto GIN index', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('GIN');
});

it('the README links to the docs page at /docs/packages/scope-pgsql', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('/docs/packages/scope-pgsql');
});

it(
    'the README notes that ScopedSelect and ScopedWhere are not shipped (deferred — selectRaw/whereRaw not yet in marko/database)',
    function (): void {
        $readmePath = dirname(__DIR__, 2) . '/README.md';
        $content = file_get_contents($readmePath);

        expect($content)->toContain('ScopedSelect')
            ->and($content)->toContain('ScopedWhere')
            ->and($content)->toContain('selectRaw')
            ->and($content)->toContain('whereRaw');
    },
);

it('the CHANGELOG.md file exists and lists removal of PgSqlScopeSortRenderer', function (): void {
    $changelogPath = dirname(__DIR__, 2) . '/CHANGELOG.md';
    $content = file_get_contents($changelogPath);

    expect($content)->not->toBeFalsy()
        ->and($content)->toContain('PgSqlScopeSortRenderer');
});

it('the CHANGELOG.md mentions the new ScopedFieldRendererInterface', function (): void {
    $changelogPath = dirname(__DIR__, 2) . '/CHANGELOG.md';
    $content = file_get_contents($changelogPath);

    expect($content)->toContain('ScopedFieldRendererInterface');
});

it(
    'the docs page contains an ORDER BY example using ScopedOrderBy with a composite signature',
    function (): void {
        $docsPath = dirname(__DIR__, 4) . '/docs/src/content/docs/packages/scope-pgsql.md';
        $content = file_get_contents($docsPath);

        expect($content)->toContain('ScopedOrderBy')
            ->and($content)->toContain('COALESCE')
            ->and($content)->toContain("'channel'")
            ->and($content)->toContain("'locale'");
    },
);

it('the docs page notes the deferral of ScopedSelect / ScopedWhere with a brief explanation', function (): void {
    $docsPath = dirname(__DIR__, 4) . '/docs/src/content/docs/packages/scope-pgsql.md';
    $content = file_get_contents($docsPath);

    expect($content)->toContain('ScopedSelect')
        ->and($content)->toContain('ScopedWhere')
        ->and($content)->toContain('selectRaw')
        ->and($content)->toContain('whereRaw');
});

it('the docs page includes the section on running integration-destructive tests', function (): void {
    $docsPath = dirname(__DIR__, 4) . '/docs/src/content/docs/packages/scope-pgsql.md';
    $content = file_get_contents($docsPath);

    expect($content)->toContain('composer test:all')
        ->and($content)->toContain('integration');
});

it('the docs page mentions the jsonb_path_ops GIN index by name', function (): void {
    $docsPath = dirname(__DIR__, 4) . '/docs/src/content/docs/packages/scope-pgsql.md';
    $content = file_get_contents($docsPath);

    expect($content)->toContain('jsonb_path_ops');
});

it('the docs page documents the candidate cap default of 256', function (): void {
    $docsPath = dirname(__DIR__, 4) . '/docs/src/content/docs/packages/scope-pgsql.md';
    $content = file_get_contents($docsPath);

    expect($content)->toContain('256');
});

it('the CHANGELOG.md does NOT claim ScopedSelect or ScopedWhere were added', function (): void {
    $changelogPath = dirname(__DIR__, 2) . '/CHANGELOG.md';
    $content = file_get_contents($changelogPath);

    expect($content)->toBeString();

    // ScopedSelect / ScopedWhere must not appear under an ### Added heading.
    // We verify that neither class appears in the Added section of the changelog.
    // They may appear in a "Not Shipped" / deferred note — that is acceptable.
    $addedSection = '';

    assert(is_string($content));

    if (preg_match('/### Added(.+?)(?=###|\z)/s', $content, $matches)) {
        $addedSection = $matches[1];
    }

    expect($addedSection)->not->toContain('ScopedSelect')
        ->and($addedSection)->not->toContain('ScopedWhere');
});
