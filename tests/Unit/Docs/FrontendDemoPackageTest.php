<?php

declare(strict_types=1);

$readmeFile = __DIR__ . '/../../../packages/frontend-demo/README.md';
$docsFile = __DIR__ . '/../../../docs/src/content/docs/packages/frontend-demo.md';

it('the package has a README.md pointing at the docs site', function () use ($readmeFile): void {
    expect(file_exists($readmeFile))->toBeTrue();

    $content = file_get_contents($readmeFile);

    // Title (h1)
    expect($content)->toContain('# markommerce/frontend-demo');
    // One-liner description
    expect($content)->toContain('markommerce/frontend-demo');
    // Installation section
    expect($content)->toContain('## Installation');
    expect($content)->toContain('composer require-dev');
    // Quick Example section
    expect($content)->toContain('## Quick Example');
    // Documentation link pointing at the docs site
    expect($content)->toContain('## Documentation');
    expect($content)->toContain('https://markommerce.dev/docs/packages/frontend-demo/');
});

it('the docs page exists at docs/src/content/docs/packages/frontend-demo.md', function () use ($docsFile): void {
    expect(file_exists($docsFile))->toBeTrue();
});

it('the docs page intro paragraph states this package is a reference and dev-only', function () use ($docsFile): void {
    $content = file_get_contents($docsFile);

    expect($content)->toContain('title: markommerce/frontend-demo');
    expect($content)->toContain('description:');
    // Intro must mention reference role and dev-only nature
    expect($content)->toMatch('/reference|smoke test/i');
    expect($content)->toMatch('/dev.only|development.only|require.dev/i');
});

it('the docs page has an Installation section showing composer require-dev and npm install', function () use ($docsFile): void {
    $content = file_get_contents($docsFile);

    expect($content)->toContain('## Installation');
    expect($content)->toContain('composer require-dev');
    expect($content)->toContain('markommerce/frontend-demo');
    expect($content)->toContain('npm install');
});

it('the docs page has a Configuration section documenting frontend_demo.enabled', function () use ($docsFile): void {
    $content = file_get_contents($docsFile);

    expect($content)->toContain('## Configuration');
    expect($content)->toContain('frontend_demo.enabled');
});

it('the docs page has a Usage section explaining how to visit /markommerce/_demo', function () use ($docsFile): void {
    $content = file_get_contents($docsFile);

    expect($content)->toContain('## Usage');
    expect($content)->toContain('/markommerce/_demo');
});

it('the docs page documents the full end-to-end consumer-app integration (add markommerce/frontend-demo as a path repo in the consuming app\'s composer.json, set frontend_demo.enabled=true, export MARKOMMERCE_CONSUMER_PUBLIC, run npm run dev)', function () use ($docsFile): void {
    $content = file_get_contents($docsFile);

    expect($content)->toContain('composer.json');
    expect($content)->toContain('frontend_demo.enabled');
    expect($content)->toContain('MARKOMMERCE_CONSUMER_PUBLIC');
    expect($content)->toContain('npm run dev');
    // Path repository pattern
    expect($content)->toMatch('/path.*repo|repositories/i');
});

it('the docs page shows the registerBase + addMixin snippet for the counter and the LabelSuffixMixin', function () use ($docsFile): void {
    $content = file_get_contents($docsFile);

    expect($content)->toContain('registerBase');
    expect($content)->toContain('addMixin');
    expect($content)->toContain('markommerce-counter');
    expect($content)->toContain('LabelSuffixMixin');
});

it('the docs page links to the writing-a-markommerce-frontend-module guide', function () use ($docsFile): void {
    $content = file_get_contents($docsFile);

    expect($content)->toContain('writing-a-frontend-module');
});

it('the docs page passes the existing DocsStandards Pest tests', function () use ($docsFile): void {
    $content = file_get_contents($docsFile);

    // Must have frontmatter with title and description
    expect($content)->toMatch('/^---\ntitle:/');
    expect($content)->toContain('description:');
    // Must not have ## Overview heading
    expect($content)->not->toContain('## Overview');
    // Must have intro paragraph (content after frontmatter closing ---)
    $withoutFrontmatter = preg_replace('/^---.*?---\n/s', '', $content);
    expect(trim($withoutFrontmatter))->not->toBeEmpty();
});
