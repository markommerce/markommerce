<?php

declare(strict_types=1);

it('layouts.css wraps all rules in @layer theme', function (): void {
    $cssPath = dirname(__DIR__, 2) . '/resources/css/layouts.css';

    expect(file_exists($cssPath))->toBeTrue();

    $contents = file_get_contents($cssPath);
    expect($contents)->toMatch('/@layer theme\s*\{/');
});

it(
    'layouts.css does not define .mk-layout-1col or .mk-layout-2col-* (replaced by mk-container and mk-sidebar primitives)',
    function (): void {
        $cssPath = dirname(__DIR__, 2) . '/resources/css/layouts.css';
        $contents = file_get_contents($cssPath);

        expect($contents)->not->toContain('.mk-layout-1col');
        expect($contents)->not->toContain('.mk-layout-2col-left');
        expect($contents)->not->toContain('.mk-layout-2col-right');
    },
);

it('layouts.css defines responsive grid columns for .mk-layout-3col using --mk-breakpoint-lg', function (): void {
    $cssPath = dirname(__DIR__, 2) . '/resources/css/layouts.css';
    $contents = file_get_contents($cssPath);

    expect($contents)->toContain('.mk-layout-3col');
    expect($contents)->toContain('--mk-breakpoint-lg');
    expect($contents)->toContain('240px 1fr 240px');
});

it(
    'layouts.css is exported from theme-blank package.json so import \'@markommerce/theme-blank/css/layouts.css\' resolves',
    function (): void {
        $packageJsonPath = dirname(__DIR__, 2) . '/package.json';
        $manifest = json_decode(file_get_contents($packageJsonPath), true);

        expect($manifest['exports']['./css/layouts.css'])->toBe('./resources/css/layouts.css');
    },
);
