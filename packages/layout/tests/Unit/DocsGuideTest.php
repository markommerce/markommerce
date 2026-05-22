<?php

declare(strict_types=1);

$guideFile = dirname(__DIR__, 4) . '/docs/src/content/docs/guides/working-with-layouts.md';

it('has a guide file at guides/working-with-layouts.md', function () use ($guideFile): void {
    expect(file_exists($guideFile))->toBeTrue();
});

it('has a title frontmatter field', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);
    expect($content)->not->toBeFalse();
    /** @var string $content */
    expect($content)->toContain('title:');
});

it('has a description frontmatter field', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);
    expect($content)->not->toBeFalse();
    /** @var string $content */
    expect($content)->toContain('description:');
});

it('documents defining a layout', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);
    expect($content)->not->toBeFalse();
    /** @var string $content */
    expect($content)->toContain('## Defining a Layout');
    expect($content)->toContain('new Layout(');
    expect($content)->toContain('new Place(');
});

it('documents context providers', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);
    expect($content)->not->toBeFalse();
    /** @var string $content */
    expect($content)->toContain('## Context Providers');
    expect($content)->toContain('ContextProvider');
    expect($content)->toContain('GalleryContextProvider');
});

it('documents repeat slots', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);
    expect($content)->not->toBeFalse();
    /** @var string $content */
    expect($content)->toContain('## Repeat Slots');
    expect($content)->toContain('Slot::repeat(');
    expect($content)->toContain('dataKey:');
    expect($content)->toContain('yields:');
    expect($content)->toContain('as:');
    expect($content)->toContain('children:');
});

it('documents layout extensions', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);
    expect($content)->not->toBeFalse();
    /** @var string $content */
    expect($content)->toContain('## Extending a Layout');
    expect($content)->toContain('new LayoutExtension(');
    expect($content)->toContain('InsertBefore');
    expect($content)->toContain('WrapWith');
    expect($content)->toContain('MergeProps');
    expect($content)->toContain('Remove');
});

it('documents the layout:compile command', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);
    expect($content)->not->toBeFalse();
    /** @var string $content */
    expect($content)->toContain('layout:compile');
    expect($content)->toContain('CompileIfStaleMiddleware');
});

it('links to the layout API reference', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);
    expect($content)->not->toBeFalse();
    /** @var string $content */
    expect($content)->toContain('/docs/packages/layout/');
});
