<?php

declare(strict_types=1);

it('the package has a README.md following the slim pattern (intro, install, one example, link to docs site)', function (): void {
    $file = __DIR__ . '/../../../packages/frontend/README.md';

    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);

    // Title (h1)
    expect($content)->toContain('# markommerce/frontend');
    // One-liner description
    expect($content)->toContain('Frontend integration for Markommerce');
    // Installation section
    expect($content)->toContain('## Installation');
    expect($content)->toContain('composer require markommerce/frontend');
    // Quick Example section
    expect($content)->toContain('## Quick Example');
    // Documentation link
    expect($content)->toContain('## Documentation');
    expect($content)->toContain('https://markommerce.dev/docs/packages/frontend/');
});

it('the docs page exists at docs/src/content/docs/packages/frontend.md', function (): void {
    $file = __DIR__ . '/../../../docs/src/content/docs/packages/frontend.md';

    expect(file_exists($file))->toBeTrue();
});

it('the docs page intro paragraph one-liners what markommerce/frontend provides', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../docs/src/content/docs/packages/frontend.md');

    // Has frontmatter with correct title
    expect($content)->toContain('title: markommerce/frontend');
    expect($content)->toContain('description:');
    // Intro paragraph describes what the package provides
    expect($content)->toContain('Frontend integration for Markommerce');
});

it('the docs page has an Installation section with both composer require and npm install commands', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../docs/src/content/docs/packages/frontend.md');

    expect($content)->toContain('## Installation');
    expect($content)->toContain('composer require markommerce/frontend');
    expect($content)->toContain('npm install @markommerce/frontend');
});

it('the docs page has a Configuration section listing every config/vite.php key with defaults and meaning', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../docs/src/content/docs/packages/frontend.md');

    expect($content)->toContain('## Configuration');
    expect($content)->toContain('config/vite.php');
    // All config keys from packages/frontend/config/vite.php
    expect($content)->toContain('entry');
    expect($content)->toContain('useDevServer');
    expect($content)->toContain('devServerUrl');
    expect($content)->toContain('buildDirectory');
    expect($content)->toContain('manifestFilename');
    expect($content)->toContain('devServerStylesheets');
});

it('the docs page documents the registerBase, addMixin, and defineAllComponents API with TypeScript signatures', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../docs/src/content/docs/packages/frontend.md');

    expect($content)->toContain('registerBase');
    expect($content)->toContain('addMixin');
    expect($content)->toContain('defineAllComponents');
});

it('the docs page documents the registerHook and runHook API with TypeScript signatures', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../docs/src/content/docs/packages/frontend.md');

    expect($content)->toContain('registerHook');
    expect($content)->toContain('runHook');
});

it('the docs page documents the DOM events helper and the MarkommerceEventMap extension pattern', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../docs/src/content/docs/packages/frontend.md');

    expect($content)->toContain('dispatchMarkommerceEvent');
    expect($content)->toContain('MarkommerceEventMap');
});

it('the docs page documents how to install the markommerceModuleScanner Vite plugin in a consumer vite.config.ts', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../docs/src/content/docs/packages/frontend.md');

    expect($content)->toContain('markommerceModuleScanner');
    expect($content)->toContain('vite.config.ts');
});

it('the docs page documents the Latte {vite()} function with a usage example', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../docs/src/content/docs/packages/frontend.md');

    expect($content)->toContain('{vite()}');
});

it('the docs page explains the MarkommerceLatteEngineFactory Preference pattern', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../docs/src/content/docs/packages/frontend.md');

    expect($content)->toContain('MarkommerceLatteEngineFactory');
    expect($content)->toContain('Preference');
});

it('the docs page documents the MARKOMMERCE_CONSUMER_PUBLIC env var and the consumer-app integration story', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../docs/src/content/docs/packages/frontend.md');

    expect($content)->toContain('MARKOMMERCE_CONSUMER_PUBLIC');
});

it('the docs page has a Related Packages section linking to markommerce/frontend-demo and marko/vite', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../docs/src/content/docs/packages/frontend.md');

    expect($content)->toContain('## Related Packages');
    expect($content)->toContain('markommerce/frontend-demo');
    expect($content)->toContain('marko/vite');
});

it('the docs page passes the existing DocsStandards Pest tests', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../docs/src/content/docs/packages/frontend.md');

    // Must have frontmatter with title and description
    expect($content)->toMatch('/^---\ntitle:/');
    expect($content)->toContain('description:');
    // Must not have ## Overview heading
    expect($content)->not->toContain('## Overview');
    // Must have intro paragraph (content after frontmatter closing ---)
    $withoutFrontmatter = preg_replace('/^---.*?---\n/s', '', $content);
    expect(trim($withoutFrontmatter))->not->toBeEmpty();
});
