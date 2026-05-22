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

it('adds a Handles section to the guide between Repeat Slots and Extending a Layout', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);
    expect($content)->not->toBeFalse();
    /** @var string $content */
    expect($content)->toContain('## Handles');

    $handlesPos = strpos($content, '## Handles');
    $repeatSlotsPos = strpos($content, '## Repeat Slots');
    $extendingPos = strpos($content, '## Extending a Layout');

    expect($handlesPos)->not->toBeFalse();
    expect($repeatSlotsPos)->not->toBeFalse();
    expect($extendingPos)->not->toBeFalse();
    expect($repeatSlotsPos)->toBeLessThan($handlesPos);
    expect($handlesPos)->toBeLessThan($extendingPos);
});

it('documents the default handle with the demo default.php example', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);
    expect($content)->not->toBeFalse();
    /** @var string $content */
    expect($content)->toContain('### The default handle');
    expect($content)->toContain("handle: 'default'");
    expect($content)->toContain('default.php');
    expect($content)->toContain('SitewideNoticeComponent');
});

it('documents handle inheritance with parent-child example and Remove operation', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);
    expect($content)->not->toBeFalse();
    /** @var string $content */
    expect($content)->toContain('### Inheriting a handle');
    expect($content)->toContain('inherits:');
    expect($content)->toContain('layout_demo_child');
    expect($content)->toContain('new Remove(');
});

it('documents the HandleProvider contract and ProvideHandle value object', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);
    expect($content)->not->toBeFalse();
    /** @var string $content */
    expect($content)->toContain('### Dynamic handles');
    expect($content)->toContain('HandleProvider');
    expect($content)->toContain('ProvideHandle');
    expect($content)->toContain('GalleryVariantHandleProvider');
    expect($content)->toContain('handleProviders:');
    expect($content)->toContain('ProvidesHandles');
});

it('lists the resolution order from extends through dynamic-handle merge', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);
    expect($content)->not->toBeFalse();
    /** @var string $content */
    expect($content)->toContain('### Resolution order');
    expect($content)->toContain('extends');
    expect($content)->toContain('inherits');
    expect($content)->toContain('default');
    expect($content)->toContain('dynamic');
});
