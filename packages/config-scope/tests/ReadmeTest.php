<?php

declare(strict_types=1);

it('ships a README.md containing the package name as the H1 heading', function (): void {
    $readmePath = dirname(__DIR__) . '/README.md';

    expect(file_exists($readmePath))->toBeTrue();

    $content = file_get_contents($readmePath);

    expect($content)->toContain('# markommerce/config-scope');
});
