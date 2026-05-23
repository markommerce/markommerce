<?php

declare(strict_types=1);

use Markommerce\Layout\LayoutExtension;
use Markommerce\Layout\Operation\InsertBefore;
use Markommerce\Layout\Operation\MergeProps;
use Markommerce\Layout\Operation\WrapWith;

it('it loads the extension from resources/views/layout/extensions/layout_demo_extension.php in ExtensionFileTest', function (): void {
    $extensionPath = __DIR__ . '/../../resources/views/layout/extensions/layout_demo_extension.php';

    expect(file_exists($extensionPath))->toBeTrue();

    $extension = require $extensionPath;

    expect($extension)->toBeInstanceOf(LayoutExtension::class);

    $hasInsertBefore = array_any(
        $extension->operations,
        fn ($op) => $op instanceof InsertBefore,
    );

    expect($hasInsertBefore)->toBeTrue();
});

it('it has an extension file that applies WrapWith', function (): void {
    $extension = require __DIR__ . '/../../resources/views/layout/extensions/layout_demo_extension.php';

    expect($extension)->toBeInstanceOf(LayoutExtension::class);

    $hasWrapWith = array_any(
        $extension->operations,
        fn ($op) => $op instanceof WrapWith,
    );

    expect($hasWrapWith)->toBeTrue();
});

it('it has an extension file that applies MergeProps', function (): void {
    $extension = require __DIR__ . '/../../resources/views/layout/extensions/layout_demo_extension.php';

    expect($extension)->toBeInstanceOf(LayoutExtension::class);

    $hasMergeProps = array_any(
        $extension->operations,
        fn ($op) => $op instanceof MergeProps,
    );

    expect($hasMergeProps)->toBeTrue();
});
