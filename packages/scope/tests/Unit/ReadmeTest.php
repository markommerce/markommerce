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

it('the README mentions ScopeSignature in a code example', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('ScopeSignature');
});

it('the README mentions a two-axis composite example', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain("'channel'")
        ->and($content)->toContain("'locale'");
});

it('the README links to the docs page at /docs/packages/scope', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('/docs/packages/scope');
});

it('the CHANGELOG.md file exists and contains the breaking-change list', function (): void {
    $changelogPath = dirname(__DIR__, 2) . '/CHANGELOG.md';
    $content = file_get_contents($changelogPath);

    expect($content)->not->toBeFalsy()
        ->and($content)->toContain('## [Unreleased]')
        ->and($content)->toContain('BREAKING');
});

it('the CHANGELOG.md mentions removal of the Scope class', function (): void {
    $changelogPath = dirname(__DIR__, 2) . '/CHANGELOG.md';
    $content = file_get_contents($changelogPath);

    expect($content)->toContain('Markommerce\Scope\Scope');
});

it('the CHANGELOG.md mentions removal of ScopeSortRendererInterface', function (): void {
    $changelogPath = dirname(__DIR__, 2) . '/CHANGELOG.md';
    $content = file_get_contents($changelogPath);

    expect($content)->toContain('ScopeSortRendererInterface');
});

it('the docs page contains a single-axis usage example', function (): void {
    $docsPath = dirname(__DIR__, 4) . '/docs/src/content/docs/packages/scope.md';
    $content = file_get_contents($docsPath);

    expect($content)->toContain("#[Scoped(axes: ['locale'])]")
        ->and($content)->toContain('ScopeSignature::fromArray');
});

it('the docs page contains a two-axis composite usage example', function (): void {
    $docsPath = dirname(__DIR__, 4) . '/docs/src/content/docs/packages/scope.md';
    $content = file_get_contents($docsPath);

    expect($content)->toContain("#[Scoped(axes: ['channel', 'locale'])]")
        ->and($content)->toContain("'channel' => 'b2b'")
        ->and($content)->toContain("'locale' => 'es'");
});

it('the docs page contains a three-axis composite usage example', function (): void {
    $docsPath = dirname(__DIR__, 4) . '/docs/src/content/docs/packages/scope.md';
    $content = file_get_contents($docsPath);

    expect($content)->toContain("#[Scoped(axes: ['channel', 'locale', 'market'])]");
});

it('the docs page contains a walkAt example with a single-axis signature', function (): void {
    $docsPath = dirname(__DIR__, 4) . '/docs/src/content/docs/packages/scope.md';
    $content = file_get_contents($docsPath);

    expect($content)->toContain('walkAt')
        ->and($content)->toContain('MultiAxisWalkAtNotSupportedException');
});

it('the docs page does not import Markommerce\Scope\Scope anywhere', function (): void {
    $docsPath = dirname(__DIR__, 4) . '/docs/src/content/docs/packages/scope.md';
    $content = file_get_contents($docsPath);

    expect($content)->not->toContain('Markommerce\Scope\Scope;');
});

it('the docs page lists ScopeSignature in the API Reference table', function (): void {
    $docsPath = dirname(__DIR__, 4) . '/docs/src/content/docs/packages/scope.md';
    $content = file_get_contents($docsPath);

    expect($content)->toContain('ScopeSignature')
        ->and($content)->toContain('API Reference');
});
