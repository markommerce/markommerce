<?php

declare(strict_types=1);

it('FEATURES.md Tier 3 package count reflects 18 packages', function (): void {
    $file = dirname(__DIR__, 2) . '/FEATURES.md';

    expect(file_exists($file))->toBeTrue();

    $content = (string) file_get_contents($file);

    // 18 packages is the new count after adding config-scope-pgsql to the Tier 2 headless stack
    expect($content)->toContain('18 packages');
});
