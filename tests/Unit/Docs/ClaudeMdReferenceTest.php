<?php

declare(strict_types=1);

it('appends a Documentation section to CLAUDE.md pointing to docs/DOCS-STANDARDS.md and mentioning the doc-updater agent', function (): void {
    $content = file_get_contents(__DIR__ . '/../../../CLAUDE.md');

    expect($content)->toContain('## Documentation');
    expect($content)->toContain('docs/DOCS-STANDARDS.md');
    expect($content)->toContain('doc-updater');
});
