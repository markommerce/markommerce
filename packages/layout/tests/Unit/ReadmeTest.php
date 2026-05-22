<?php

declare(strict_types=1);

it('has a README with a package description', function (): void {
    $readme = file_get_contents(__DIR__ . '/../../README.md');
    expect($readme)->not->toBeFalse();
    /** @var string $readme */
    expect($readme)->toContain('markommerce/layout');
});

it('documents the installation command', function (): void {
    $readme = file_get_contents(__DIR__ . '/../../README.md');
    expect($readme)->not->toBeFalse();
    /** @var string $readme */
    expect($readme)->toContain('composer require');
});

it('includes a layout-definition quick example', function (): void {
    $readme = file_get_contents(__DIR__ . '/../../README.md');
    expect($readme)->not->toBeFalse();
    /** @var string $readme */
    expect($readme)->toContain('```php');
});

it('documents the layout:compile command', function (): void {
    $readme = file_get_contents(__DIR__ . '/../../README.md');
    expect($readme)->not->toBeFalse();
    /** @var string $readme */
    expect($readme)->toContain('layout:compile');
});
