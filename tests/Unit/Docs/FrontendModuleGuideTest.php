<?php

declare(strict_types=1);

$guideFile = __DIR__ . '/../../../docs/src/content/docs/guides/writing-a-frontend-module.md';

it('the guide exists at the docs site Guides path documented in DOCS-STANDARDS.md', function () use ($guideFile): void {
    expect(file_exists($guideFile))->toBeTrue();
});

it('the guide explains how to scaffold a new Markommerce package with both composer.json and package.json manifests', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);

    expect($content)->toContain('composer.json');
    expect($content)->toContain('package.json');
    expect($content)->toContain('markommerce/frontend');
});

it('the guide documents the markommerce block in package.json with all required and optional fields', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);

    expect($content)->toContain('"markommerce"');
    expect($content)->toContain('"extension"');
    expect($content)->toContain('"priority"');
});

it('the guide includes a worked example of authoring a Lit component with protected template methods', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);

    expect($content)->toContain('LitElement');
    expect($content)->toContain('protected');
    expect($content)->toContain('renderLabel');
    expect($content)->toContain('renderButton');
});

it('the guide includes a worked example of registering a base via registerBase', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);

    expect($content)->toContain('registerBase');
});

it('the guide includes a worked example of authoring and registering a functional mixin via addMixin', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);

    expect($content)->toContain('addMixin');
    expect($content)->toContain('LabelSuffixMixin');
});

it('the guide includes a worked example of registering and consuming a hook via registerHook and runHook', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);

    expect($content)->toContain('registerHook');
    expect($content)->toContain('runHook');
    expect($content)->toContain('HookRegistry');
});

it('the guide includes a worked example of declaring a typed CustomEvent and extending MarkommerceEventMap', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);

    expect($content)->toContain('MarkommerceEventMap');
    expect($content)->toContain('dispatchMarkommerceEvent');
    expect($content)->toContain('CustomEvent');
});

it('the guide includes a worked example of extending core types via declare module declaration merging', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);

    expect($content)->toContain('declare module');
});

it('the guide explains the cascade layer order and how to write CSS that participates in it', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);

    expect($content)->toContain('@layer');
    expect($content)->toContain('components');
    expect($content)->toContain('layers.css');
});

it('the guide shows how to write Vitest tests for a component and a mixin', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);

    expect($content)->toContain('vitest');
    expect($content)->toContain('describe');
    expect($content)->toContain('expect');
});

it('the guide includes a "where to look next" section linking to packages/frontend-demo as the canonical reference', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);

    expect($content)->toContain('packages/frontend-demo');
    expect($content)->toContain('Where to Look Next');
});

it('the guide passes the existing DocsStandards Pest tests', function () use ($guideFile): void {
    $content = file_get_contents($guideFile);

    // Frontmatter requirements
    expect($content)->toContain('title:');
    expect($content)->toContain('description:');

    // No ## Overview heading
    expect($content)->not->toContain('## Overview');

    // Has intro paragraph (content after frontmatter, before first heading)
    $withoutFrontmatter = preg_replace('/^---.*?---\s*/s', '', $content);
    // First content should not be a heading
    $firstLine = ltrim($withoutFrontmatter ?? '');
    expect(str_starts_with($firstLine, '#'))->toBeFalse();
});
