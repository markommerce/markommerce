<?php

declare(strict_types=1);

it('README.md exists at packages/theme-blank-demo/README.md', function (): void {
    $path = __DIR__ . '/../../README.md';

    expect(file_exists($path))->toBeTrue();
});

it('README.md contains the package name markommerce/theme-blank-demo', function (): void {
    $path = __DIR__ . '/../../README.md';
    $contents = file_get_contents($path);

    expect($contents)->toContain('markommerce/theme-blank-demo');
});

it('README.md documents the /markommerce/_demo/theme-blank route URL', function (): void {
    $path = __DIR__ . '/../../README.md';
    $contents = file_get_contents($path);

    expect($contents)->toContain('/markommerce/_demo/theme-blank');
});

it('README.md describes how to enable the route via config/theme_blank_demo.php', function (): void {
    $path = __DIR__ . '/../../README.md';
    $contents = file_get_contents($path);

    expect($contents)->toContain('config/theme_blank_demo.php');
    expect($contents)->toContain("'enabled'");
});

it('README.md links to the markommerce.dev docs site', function (): void {
    $path = __DIR__ . '/../../README.md';
    $contents = file_get_contents($path);

    expect($contents)->toContain('markommerce.dev/docs/packages/theme-blank-demo');
});

it('docs page exists at docs/src/content/docs/packages/theme-blank-demo.md', function (): void {
    $path = __DIR__ . '/../../../../docs/src/content/docs/packages/theme-blank-demo.md';

    expect(file_exists($path))->toBeTrue();
});

it('docs page has frontmatter title and description', function (): void {
    $path = __DIR__ . '/../../../../docs/src/content/docs/packages/theme-blank-demo.md';
    $contents = file_get_contents($path);

    expect($contents)->toContain('title:');
    expect($contents)->toContain('description:');
    expect($contents)->toContain('markommerce/theme-blank-demo');
});

it(
    "docs page has ## Installation, ## Usage, ## What's on the page, ## Architecture, ## Related sections",
    function (): void {
        $path = __DIR__ . '/../../../../docs/src/content/docs/packages/theme-blank-demo.md';
        $contents = file_get_contents($path);

        expect($contents)->toContain('## Installation');
        expect($contents)->toContain('## Usage');
        expect($contents)->toContain("## What's on the page");
        expect($contents)->toContain('## Architecture');
        expect($contents)->toContain('## Related');
    },
);
