<?php

declare(strict_types=1);

it('lists doc-updater as an active bullet under the post-implementation section of pipeline.md', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../.claude/pipeline.md');

    expect($content)->toContain('- doc-updater');
});

it('removes the commented-out standards-enforcer placeholder line', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../.claude/pipeline.md');

    expect($content)->not->toContain('<!-- - standards-enforcer -->');
});

it('keeps devils-advocate as the only entry under the post-plan section', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../.claude/pipeline.md');

    expect($content)->toContain('- devils-advocate');
});

it('preserves the file\'s existing header and explanation paragraph', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../.claude/pipeline.md');

    expect($content)
        ->toContain('# Pipeline')
        ->toContain('Configure which agents run at each phase of the development workflow.');
});

it('places the doc-updater bullet at a file offset greater than the post-implementation heading', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../.claude/pipeline.md');

    expect(strpos($content, '- doc-updater'))->toBeGreaterThan(strpos($content, '## post-implementation'));
});

it('places the post-implementation heading at a file offset greater than the post-plan heading', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../.claude/pipeline.md');

    expect(strpos($content, '## post-implementation'))->toBeGreaterThan(strpos($content, '## post-plan'));
});
