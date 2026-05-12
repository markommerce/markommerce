<?php

declare(strict_types=1);

it('creates the .claude/agents directory', function (): void {
    expect(is_dir(__DIR__ . '/../../../.claude/agents'))->toBeTrue();
});

it('creates .claude/agents/doc-updater.md with frontmatter name doc-updater', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../.claude/agents/doc-updater.md');

    expect($content)->toContain('name: doc-updater');
});

it('declares the model as sonnet and tools as Read Edit Glob Grep Write in the frontmatter', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../.claude/agents/doc-updater.md');

    expect($content)
        ->toContain('model: sonnet')
        ->toMatch('/tools:\s*Read,\s*Edit,\s*Glob,\s*Grep,\s*Write/');
});

it('imports docs/DOCS-STANDARDS.md via the @docs/DOCS-STANDARDS.md auto-load reference in the body', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../.claude/agents/doc-updater.md');

    expect($content)->toContain('@docs/DOCS-STANDARDS.md');
});

it('describes the agent\'s process steps Identify Affected Packages Determine What Changed Find Relevant Docs Update or Create Docs and Output', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../.claude/agents/doc-updater.md');

    expect($content)
        ->toContain('Identify Affected Packages')
        ->toContain('Determine What Changed')
        ->toContain('Find Relevant Docs')
        ->toContain('Update or Create Docs')
        ->toContain('Output');
});

it('declares the DOCS_UPDATED and DOCS_CURRENT output sentinels', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../.claude/agents/doc-updater.md');

    expect($content)
        ->toContain('DOCS_UPDATED')
        ->toContain('DOCS_CURRENT');
});

it('instructs the agent to slim package READMEs to the format defined in DOCS-STANDARDS.md', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../.claude/agents/doc-updater.md');

    expect($content)
        ->toContain('slim README format')
        ->toContain('DOCS-STANDARDS.md');
});

it('adapts references from Marko to markommerce throughout the body (no occurrences of marko/cache or marko/database remain)', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../.claude/agents/doc-updater.md');

    expect($content)
        ->not->toContain('marko/cache')
        ->not->toContain('marko/database')
        ->toContain('markommerce');
});

it('documents that the orchestrator passes a newline-separated list of project-relative file paths', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../.claude/agents/doc-updater.md');

    expect($content)
        ->toContain('newline-separated list of project-relative file paths');
});

it('instructs the agent to output DOCS_CURRENT immediately when no changed file lies under packages/{name}/src or packages/{name}/README.md or packages/{name}/composer.json', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../.claude/agents/doc-updater.md');

    expect($content)
        ->toContain('packages/*/src/')
        ->toContain('packages/*/README.md')
        ->toContain('packages/*/composer.json')
        ->toContain('DOCS_CURRENT');
});
