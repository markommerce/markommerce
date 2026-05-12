<?php

declare(strict_types=1);

it('creates docs/DOCS-STANDARDS.md at the docs app root', function (): void {
    expect(file_exists(__DIR__ . '/../../../docs/DOCS-STANDARDS.md'))->toBeTrue();
});

it('includes the Formatting Rules section covering headings frontmatter code blocks links PHP code examples and CLI commands', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../docs/DOCS-STANDARDS.md');

    expect($content)->toContain('## Formatting Rules');
    expect($content)->toContain('### Headings');
    expect($content)->toContain('### Frontmatter');
    expect($content)->toContain('### Intro Paragraph');
    expect($content)->toContain('### Code Blocks');
    expect($content)->toContain('### Links');
    expect($content)->toContain('### PHP Code Examples');
    expect($content)->toContain('### Punctuation');
    expect($content)->toContain('### Tables');
    // CLI Commands subsection is intentionally absent
    expect($content)->not->toContain('### CLI Commands');
});

it('includes the Package README Format section with the slim README structure (title installation quick example documentation link)', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../docs/DOCS-STANDARDS.md');

    expect($content)->toContain('## Package README Format');
    expect($content)->toContain('## Migrating Package READMEs to Docs');
    // Slim README structure elements
    expect($content)->toContain('Title');
    expect($content)->toContain('Installation');
    expect($content)->toContain('Quick Example');
    expect($content)->toContain('Documentation');
});

it('replaces every Marko-specific package reference with the markommerce equivalent (markommerce/catalog appears at least once and marko/cache does not)', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../docs/DOCS-STANDARDS.md');

    expect($content)->toContain('markommerce/catalog');
    expect($content)->not->toContain('marko/cache');
    expect($content)->not->toContain('marko/database');
});

it('includes the Content Principles section with the No pseudo-documentation rule', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../docs/DOCS-STANDARDS.md');

    expect($content)->toContain('## Content Principles');
    expect($content)->toContain('No pseudo-documentation');
});

it('documents all five content sections Getting Started Concepts Packages Guides Tutorials with Purpose Audience and Content style for each', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../docs/DOCS-STANDARDS.md');

    $sections = ['Getting Started', 'Concepts', 'Packages', 'Guides', 'Tutorials'];
    foreach ($sections as $section) {
        expect($content)->toContain('### ' . $section);
        expect($content)->toContain('**Purpose:**');
        expect($content)->toContain('**Audience:**');
        expect($content)->toContain('**Content style:**');
    }
});
