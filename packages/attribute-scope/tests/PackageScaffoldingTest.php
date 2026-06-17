<?php

declare(strict_types=1);
use Markommerce\AttributeScope\ScopedOptionLabelResolver;

it('autoloads a class from the Markommerce\AttributeScope namespace', function (): void {
    expect(class_exists(ScopedOptionLabelResolver::class))->toBeTrue();
});

it('marks attribute-scope as a marko module in composer extra', function (): void {
    $composerPath = dirname(__DIR__) . '/composer.json';
    $composer = json_decode(file_get_contents($composerPath), true);

    expect($composer['extra']['marko']['module'])->toBeTrue();
});

it('declares markommerce/scope as a dependency of both packages', function (): void {
    $attributeScopeComposerPath = dirname(__DIR__) . '/composer.json';
    $attributeScopeComposer = json_decode(file_get_contents($attributeScopeComposerPath), true);

    $catalogAttributeScopeComposerPath = dirname(__DIR__, 2) . '/catalog-attribute-scope/composer.json';
    $catalogAttributeScopeComposer = json_decode(file_get_contents($catalogAttributeScopeComposerPath), true);

    expect($attributeScopeComposer['require'])->toHaveKey('markommerce/scope')
        ->and($attributeScopeComposer['require']['markommerce/scope'])->toBe('self.version')
        ->and($catalogAttributeScopeComposer['require'])->toHaveKey('markommerce/scope')
        ->and($catalogAttributeScopeComposer['require']['markommerce/scope'])->toBe('self.version');
});
