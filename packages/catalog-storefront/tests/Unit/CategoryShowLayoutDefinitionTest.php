<?php

declare(strict_types=1);

use Markommerce\Layout\Layout;
use Markommerce\ThemeBlank\Layout\TwoColumnsLeftLayout;

function categoryShowLayoutLoad(): Layout
{
    $path = dirname(__DIR__, 2) . '/layout/category_show.php';

    return require $path;
}

function categoryPageFragmentLayoutLoad(): Layout
{
    $path = dirname(__DIR__, 2) . '/layout/category_page_fragment.php';

    return require $path;
}

it('extends the two-columns-left theme layout for the category page', function (): void {
    $layout = categoryShowLayoutLoad();

    expect($layout->extends)->toBe(TwoColumnsLeftLayout::class);
});

it('keeps the product grid placement in the content slot', function (): void {
    $layout = categoryShowLayoutLoad();

    expect($layout->slots)->toHaveKey('content');
    expect($layout->slots['content'])->not->toBeEmpty();
});

it('leaves the category page fragment layout single-column', function (): void {
    $layout = categoryPageFragmentLayoutLoad();

    expect($layout->extends)->toBeNull();
    expect($layout->slots)->toHaveKey('content');
    expect($layout->slots)->not->toHaveKey('sidebar-left');
});
