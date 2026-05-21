<?php

declare(strict_types=1);

it(
    'the catalog README documents the package name and a one-line summary',
    function (): void {
        $readmePath = dirname(__DIR__, 2) . '/README.md';
        $content = file_get_contents($readmePath);

        expect($content)->toContain('# markommerce/catalog');
    },
);

it('the catalog README includes an installation section', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('## Installation')
        ->and($content)->toContain('composer require markommerce/catalog');
});

it('the catalog README documents the Product and Category entities', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('Product')
        ->and($content)->toContain('Category')
        ->and($content)->toContain('sku')
        ->and($content)->toContain('locale');
});

it('the catalog README documents the storefront category route', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('/catalog/category/{id}');
});

it('the catalog README documents the catalog seeder', function (): void {
    $readmePath = dirname(__DIR__, 2) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('catalog')
        ->and($content)->toContain('seeder')
        ->and($content)->toContain('locale:de')
        ->and($content)->toContain('locale:fr');
});
