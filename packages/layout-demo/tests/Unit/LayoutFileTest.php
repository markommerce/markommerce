<?php

declare(strict_types=1);

use Markommerce\Layout\Layout;
use Markommerce\Layout\Place;
use Markommerce\Layout\Provide;
use Markommerce\Layout\Slot;
use Markommerce\LayoutDemo\Controller\LayoutDemoController;
use Markommerce\ThemeBlank\Layout\OneColumnLayout;

it('it loads layout_demo from resources/views/layout/layout_demo.php in LayoutFileTest', function (): void {
    $layoutPath = __DIR__ . '/../../resources/views/layout/layout_demo.php';

    expect(file_exists($layoutPath))->toBeTrue();

    $layout = require $layoutPath;

    expect($layout)->toBeInstanceOf(Layout::class);
    expect($layout->handle)->toBe([LayoutDemoController::class, 'show']);
});

it('it asserts the layout file exists at the new resources/views/layout path', function (): void {
    $layoutPath = __DIR__ . '/../../resources/views/layout/layout_demo.php';

    expect(file_exists($layoutPath))->toBeTrue();
});

it('it has no remaining files under packages/layout-demo/layout/', function (): void {
    $oldLayoutDir = __DIR__ . '/../../layout';

    expect(is_dir($oldLayoutDir))->toBeFalse();
});

it('it has a Layout that extends OneColumnLayout', function (): void {
    $layout = require __DIR__ . '/../../resources/views/layout/layout_demo.php';

    expect($layout)->toBeInstanceOf(Layout::class);
    expect($layout->extends)->toBe(OneColumnLayout::class);
});

it('it has a Layout with a context Provide', function (): void {
    $layout = require __DIR__ . '/../../resources/views/layout/layout_demo.php';

    expect($layout)->toBeInstanceOf(Layout::class);
    expect($layout->context)->not->toBeEmpty();
    expect($layout->context[0])->toBeInstanceOf(Provide::class);
});

it('it has a Layout with a repeat slot', function (): void {
    $layout = require __DIR__ . '/../../resources/views/layout/layout_demo.php';

    expect($layout)->toBeInstanceOf(Layout::class);

    // Walk the slots to find a Slot::repeat instance
    $hasRepeatSlot = false;
    $checkSlots = function (array $slots) use (&$hasRepeatSlot, &$checkSlots): void {
        foreach ($slots as $value) {
            if ($value instanceof Slot) {
                $hasRepeatSlot = true;
                return;
            }
            if ($value instanceof Place) {
                $checkSlots($value->slots);
            }
            if (is_array($value)) {
                $checkSlots($value);
            }
        }
    };
    $checkSlots($layout->slots);

    expect($hasRepeatSlot)->toBeTrue();
});
