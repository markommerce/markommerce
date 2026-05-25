<?php

declare(strict_types=1);

use Markommerce\Layout\Contracts\LayoutDefinition;
use Markommerce\Layout\Layout;
use Markommerce\ThemeBlank\Layout\EmptyLayout;
use Markommerce\ThemeBlank\Layout\OneColumnLayout;
use Markommerce\ThemeBlank\Layout\ThreeColumnsLayout;
use Markommerce\ThemeBlank\Layout\TwoColumnsLeftLayout;
use Markommerce\ThemeBlank\Layout\TwoColumnsRightLayout;

it('defines OneColumnLayout as a LayoutDefinition', function (): void {
    expect(OneColumnLayout::class)->toImplement(LayoutDefinition::class);
});

it('defines TwoColumnsLeftLayout as a LayoutDefinition', function (): void {
    expect(TwoColumnsLeftLayout::class)->toImplement(LayoutDefinition::class);
});

it('defines TwoColumnsRightLayout as a LayoutDefinition', function (): void {
    expect(TwoColumnsRightLayout::class)->toImplement(LayoutDefinition::class);
});

it('defines ThreeColumnsLayout as a LayoutDefinition', function (): void {
    expect(ThreeColumnsLayout::class)->toImplement(LayoutDefinition::class);
});

it('defines EmptyLayout as a LayoutDefinition', function (): void {
    expect(EmptyLayout::class)->toImplement(LayoutDefinition::class);
});

it('declares a content slot on the one-column layout', function (): void {
    $layout = OneColumnLayout::define();

    expect($layout)->toBeInstanceOf(Layout::class)
        ->and($layout->slots)->toHaveKey('content');
});

it('declares content and sidebar-left slots on the two-columns-left layout', function (): void {
    $layout = TwoColumnsLeftLayout::define();

    expect($layout)->toBeInstanceOf(Layout::class)
        ->and($layout->slots)->toHaveKey('content')
        ->and($layout->slots)->toHaveKey('sidebar-left');
});

it('declares content, sidebar-left and sidebar-right slots on the three-columns layout', function (): void {
    $layout = ThreeColumnsLayout::define();

    expect($layout)->toBeInstanceOf(Layout::class)
        ->and($layout->slots)->toHaveKey('content')
        ->and($layout->slots)->toHaveKey('sidebar-left')
        ->and($layout->slots)->toHaveKey('sidebar-right');
});

it('returns a Layout with no route handle from a base layout define method', function (): void {
    $layouts = [
        OneColumnLayout::define(),
        TwoColumnsLeftLayout::define(),
        TwoColumnsRightLayout::define(),
        ThreeColumnsLayout::define(),
        EmptyLayout::define(),
    ];

    foreach ($layouts as $layout) {
        expect($layout->handle)->toBeNull();
    }
});

it('serves as an extends target for another layout', function (): void {
    $childLayout = new Layout(
        handle: 'catalog_product_view',
        extends: OneColumnLayout::class,
        context: [],
        slots: [],
    );

    expect($childLayout->extends)->toBe(OneColumnLayout::class);

    $parentLayout = $childLayout->extends::define();
    expect($parentLayout)->toBeInstanceOf(Layout::class)
        ->and($parentLayout->template)->toBe('theme-blank::layout/1column')
        ->and($parentLayout->slots)->toHaveKey('content');
});

it('no longer depends on marko/layout in composer.json', function (): void {
    $composerJsonPath = dirname(__DIR__, 3) . '/composer.json';
    $contents = file_get_contents($composerJsonPath);
    expect($contents)->not->toBeFalse();
    /** @var string $contents */
    $composerJson = json_decode($contents, true);

    expect($composerJson['require'])->not->toHaveKey('marko/layout');
});
