<?php

declare(strict_types=1);

it('creates the docs/src/content/docs directory tree', function (): void {
    $base = __DIR__ . '/../../../docs/src/content/docs';

    expect(is_dir($base))->toBeTrue();
    expect(is_dir($base . '/getting-started'))->toBeTrue();
    expect(is_dir($base . '/concepts'))->toBeTrue();
    expect(is_dir($base . '/packages'))->toBeTrue();
    expect(is_dir($base . '/guides'))->toBeTrue();
    expect(is_dir($base . '/tutorials'))->toBeTrue();
});

it('creates an index.mdx with Starlight splash template frontmatter and a hero block', function (): void {
    $file = __DIR__ . '/../../../docs/src/content/docs/index.mdx';

    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);

    expect($content)->toContain('title: MARKOMMERCE');
    expect($content)->toContain('template: splash');
    expect($content)->toContain('hero:');
    expect($content)->toContain('tagline:');
    expect($content)->toContain('actions:');
    expect($content)->toContain('Get Started');
    expect($content)->toContain('import { Card, CardGrid } from \'@astrojs/starlight/components\';');
    expect($content)->toContain('<CardGrid>');
    expect($content)->toContain('<Card ');
});

it('creates getting-started/introduction.md with title and description frontmatter', function (): void {
    $file = __DIR__ . '/../../../docs/src/content/docs/getting-started/introduction.md';

    expect(file_exists($file))->toBeTrue();

    $content = file_get_contents($file);

    expect($content)->toContain('title: Introduction');
    expect($content)->toContain('description: What markommerce is and why it exists.');
    expect($content)->toContain('## What Markommerce Provides');
    expect($content)->toContain('## What Markommerce Is Not');
    expect($content)->toContain('## Next Steps');
});

it('creates a placeholder .gitkeep in concepts packages guides and tutorials sections', function (): void {
    $base = __DIR__ . '/../../../docs/src/content/docs';

    expect(file_exists($base . '/concepts/.gitkeep'))->toBeTrue();
    expect(file_exists($base . '/packages/.gitkeep'))->toBeTrue();
    expect(file_exists($base . '/guides/.gitkeep'))->toBeTrue();
    expect(file_exists($base . '/tutorials/.gitkeep'))->toBeTrue();
});

it('does not create any Astro or Node config files (no package.json no astro.config.mjs no content.config.ts)', function (): void {
    $docsRoot = __DIR__ . '/../../../docs';

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($docsRoot, FilesystemIterator::SKIP_DOTS),
    );

    $found = [];
    foreach ($iterator as $file) {
        $basename = $file->getBasename();
        if (in_array($basename, ['package.json', 'astro.config.mjs', 'content.config.ts'], true)) {
            $found[] = $basename;
        }
    }

    expect($found)->toBeEmpty();
});
