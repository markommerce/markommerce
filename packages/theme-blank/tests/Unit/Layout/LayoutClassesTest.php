<?php

declare(strict_types=1);

use Marko\Layout\Attributes\Component;
use Markommerce\ThemeBlank\Layout\EmptyLayout;
use Markommerce\ThemeBlank\Layout\OneColumnLayout;
use Markommerce\ThemeBlank\Layout\ThreeColumnsLayout;
use Markommerce\ThemeBlank\Layout\TwoColumnsLeftLayout;
use Markommerce\ThemeBlank\Layout\TwoColumnsRightLayout;

it('declares OneColumnLayout with a Component attribute pointing at theme-blank::layout/1column and the content slot', function (): void {
    $reflection = new ReflectionClass(OneColumnLayout::class);
    $attributes = $reflection->getAttributes(Component::class);

    expect($attributes)->toHaveCount(1);

    $component = $attributes[0]->newInstance();

    expect($component->template)->toBe('theme-blank::layout/1column');
    expect($component->slots)->toBe(['content']);
});

it('declares TwoColumnsLeftLayout with a Component attribute pointing at theme-blank::layout/2columns-left and the content and sidebar-left slots', function (): void {
    $reflection = new ReflectionClass(TwoColumnsLeftLayout::class);
    $attributes = $reflection->getAttributes(Component::class);

    expect($attributes)->toHaveCount(1);

    $component = $attributes[0]->newInstance();

    expect($component->template)->toBe('theme-blank::layout/2columns-left');
    expect($component->slots)->toBe(['content', 'sidebar-left']);
});

it('declares TwoColumnsRightLayout with a Component attribute pointing at theme-blank::layout/2columns-right and the content and sidebar-right slots', function (): void {
    $reflection = new ReflectionClass(TwoColumnsRightLayout::class);
    $attributes = $reflection->getAttributes(Component::class);

    expect($attributes)->toHaveCount(1);

    $component = $attributes[0]->newInstance();

    expect($component->template)->toBe('theme-blank::layout/2columns-right');
    expect($component->slots)->toBe(['content', 'sidebar-right']);
});

it('declares ThreeColumnsLayout with a Component attribute pointing at theme-blank::layout/3columns and the content, sidebar-left and sidebar-right slots', function (): void {
    $reflection = new ReflectionClass(ThreeColumnsLayout::class);
    $attributes = $reflection->getAttributes(Component::class);

    expect($attributes)->toHaveCount(1);

    $component = $attributes[0]->newInstance();

    expect($component->template)->toBe('theme-blank::layout/3columns');
    expect($component->slots)->toBe(['content', 'sidebar-left', 'sidebar-right']);
});

it('declares EmptyLayout with a Component attribute pointing at theme-blank::layout/empty and the content slot', function (): void {
    $reflection = new ReflectionClass(EmptyLayout::class);
    $attributes = $reflection->getAttributes(Component::class);

    expect($attributes)->toHaveCount(1);

    $component = $attributes[0]->newInstance();

    expect($component->template)->toBe('theme-blank::layout/empty');
    expect($component->slots)->toBe(['content']);
});
