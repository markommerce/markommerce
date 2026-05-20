<?php

declare(strict_types=1);

use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;

it('creates a ScopeAxis with name and hierarchy reference', function (): void {
    $hierarchy = new ScopeHierarchy(['global']);
    $axis = new ScopeAxis(name: 'geo', hierarchy: $hierarchy, default: 'global');

    expect($axis->name)->toBe('geo')
        ->and($axis->hierarchy)->toBe($hierarchy);
});

it('constructs a ScopeAxis with a name, hierarchy, and default scope', function (): void {
    $hierarchy = new ScopeHierarchy(['global', 'global.us']);
    $axis = new ScopeAxis(name: 'store', hierarchy: $hierarchy, default: 'global');

    expect($axis->name)->toBe('store')
        ->and($axis->hierarchy)->toBe($hierarchy)
        ->and($axis->default)->toBe('global');
});

it('exposes the default scope as a public readonly property', function (): void {
    $hierarchy = new ScopeHierarchy(['global', 'global.eu']);
    $axis = new ScopeAxis(name: 'geo', hierarchy: $hierarchy, default: 'global');

    $reflection = new ReflectionProperty($axis, 'default');

    expect($axis->default)->toBe('global')
        ->and($reflection->isPublic())->toBeTrue()
        ->and($reflection->isReadOnly())->toBeTrue();
});
