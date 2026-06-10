<?php

declare(strict_types=1);

it(
    'does not include packages/config/src/Resolution/OverrideMatcher.php on the filesystem after task completes',
    function (): void {
        // __DIR__ = packages/config/tests/Unit
        // go up 2 levels to packages/config, then into src/Resolution
        $path = dirname(__DIR__, 2) . '/src/Resolution/OverrideMatcher.php';
        expect(file_exists($path))->toBeFalse();
    },
);

it(
    'does not include packages/config/src/Exceptions/AxisNotDeclaredException.php on the filesystem after task completes',
    function (): void {
        $path = dirname(__DIR__, 2) . '/src/Exceptions/AxisNotDeclaredException.php';
        expect(file_exists($path))->toBeFalse();
    },
);

it(
    'does not include packages/config/tests/Unit/Resolution/OverrideMatcherTest.php on the filesystem after task completes',
    function (): void {
        $overrideMatcherTestPath = __DIR__ . '/Resolution/OverrideMatcherTest.php';
        expect(file_exists($overrideMatcherTestPath))->toBeFalse();
    },
);

it(
    'does not include packages/config/tests/Fakes/FakeScopeRegistry.php on the filesystem after task completes',
    function (): void {
        $path = dirname(__DIR__, 2) . '/tests/Fakes/FakeScopeRegistry.php';
        expect(file_exists($path))->toBeFalse();
    },
);

it(
    'does not bind ScopeRegistryInterface inside bootModuleContainer in tests/Unit/ModulePhpTest.php',
    function (): void {
        $file = __DIR__ . '/ModulePhpTest.php';
        $contents = file_get_contents($file);

        expect($contents)->not->toContain('ScopeRegistryInterface');
    },
);
