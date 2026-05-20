<?php

declare(strict_types=1);

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Markommerce\Scope\Storage\HasScopes;
use Markommerce\Scope\Storage\HasScopesInterface;

// ─── Fixture ─────────────────────────────────────────────────────────────────

#[Table(name: 'trait_products')]
class TraitProduct extends Entity implements HasScopesInterface
{
    use HasScopes;

    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;
}

// ─── Tests ───────────────────────────────────────────────────────────────────

it('exposes the HasScopesInterface override methods via the trait', function (): void {
    $entity = new TraitProduct();

    expect(method_exists($entity, 'setOverride'))->toBeTrue()
        ->and(method_exists($entity, 'override'))->toBeTrue()
        ->and(method_exists($entity, 'hasOverride'))->toBeTrue()
        ->and(method_exists($entity, 'clearOverride'))->toBeTrue()
        ->and(method_exists($entity, 'overrides'))->toBeTrue();
});

it('stores multiple overrides keyed by scopeKey and property', function (): void {
    $entity = new TraitProduct();
    $entity->setOverride('geo:eu.de', 'name', 'Hemd');
    $entity->setOverride('locale:de', 'name', 'Hallo');
    $entity->setOverride('geo:eu.de', 'price', 19.99);

    expect($entity->overrides())->toBe([
        'geo:eu.de' => ['name' => 'Hemd', 'price' => 19.99],
        'locale:de' => ['name' => 'Hallo'],
    ]);
});

it('returns null for unknown scopeKey or property via override', function (): void {
    $entity = new TraitProduct();

    expect($entity->override('geo:eu.de', 'name'))->toBeNull()
        ->and($entity->override('unknown', 'price'))->toBeNull();
});

it('returns false for hasOverride when no override exists', function (): void {
    $entity = new TraitProduct();

    expect($entity->hasOverride('geo:eu.de', 'name'))->toBeFalse();
});

it('distinguishes an explicit null override from no override via hasOverride', function (): void {
    $entity = new TraitProduct();

    expect($entity->hasOverride('geo:eu.de', 'name'))->toBeFalse();

    $entity->setOverride('geo:eu.de', 'name', null);

    expect($entity->hasOverride('geo:eu.de', 'name'))->toBeTrue()
        ->and($entity->override('geo:eu.de', 'name'))->toBeNull();
});

it('clears a single property override leaving others intact', function (): void {
    $entity = new TraitProduct();
    $entity->setOverride('geo:eu.de', 'name', 'Hemd');
    $entity->setOverride('geo:eu.de', 'price', 19.99);
    $entity->clearOverride('geo:eu.de', 'name');

    expect($entity->override('geo:eu.de', 'name'))->toBeNull()
        ->and($entity->override('geo:eu.de', 'price'))->toBe(19.99);
});

it('sets scopes to null when the last override is cleared', function (): void {
    $entity = new TraitProduct();
    $entity->setOverride('geo:eu.de', 'name', 'Hemd');
    $entity->clearOverride('geo:eu.de', 'name');

    expect($entity->scopes)->toBeNull();
});

it(
    'declares a json nullable Column attribute on the scopes property when reflected via a consuming class',
    function (): void {
        $reflection = new ReflectionClass(TraitProduct::class);

        expect($reflection->hasProperty('scopes'))->toBeTrue();

        $property = $reflection->getProperty('scopes');
        $attributes = $property->getAttributes(Column::class);

        expect($attributes)->toHaveCount(1);

        $column = $attributes[0]->newInstance();

        expect($column->name)->toBe('scopes')
            ->and($column->type)->toBe('json')
            ->and($column->nullable)->toBeTrue();
    },
);

it('a class using HasScopes can satisfy the HasScopesInterface contract', function (): void {
    $entity = new TraitProduct();

    expect($entity)->toBeInstanceOf(HasScopesInterface::class);
});

it('HasScopesInterface declares the override methods', function (): void {
    $reflection = new ReflectionClass(HasScopesInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('setOverride'))->toBeTrue()
        ->and($reflection->hasMethod('override'))->toBeTrue()
        ->and($reflection->hasMethod('hasOverride'))->toBeTrue()
        ->and($reflection->hasMethod('clearOverride'))->toBeTrue()
        ->and($reflection->hasMethod('overrides'))->toBeTrue();
});

it('HasScopesInterface::setOverride parameter is named $signature', function (): void {
    $reflection = new ReflectionClass(HasScopesInterface::class);
    $method = $reflection->getMethod('setOverride');
    $firstParam = $method->getParameters()[0];

    expect($firstParam->getName())->toBe('signature');
});

it('HasScopesInterface::override parameter is named $signature', function (): void {
    $reflection = new ReflectionClass(HasScopesInterface::class);
    $method = $reflection->getMethod('override');
    $firstParam = $method->getParameters()[0];

    expect($firstParam->getName())->toBe('signature');
});

it('HasScopesInterface::hasOverride parameter is named $signature', function (): void {
    $reflection = new ReflectionClass(HasScopesInterface::class);
    $method = $reflection->getMethod('hasOverride');
    $firstParam = $method->getParameters()[0];

    expect($firstParam->getName())->toBe('signature');
});

it('HasScopesInterface::clearOverride parameter is named $signature', function (): void {
    $reflection = new ReflectionClass(HasScopesInterface::class);
    $method = $reflection->getMethod('clearOverride');
    $firstParam = $method->getParameters()[0];

    expect($firstParam->getName())->toBe('signature');
});

it('all existing HasScopesTraitTest assertions still pass with the renamed parameter', function (): void {
    $entity = new TraitProduct();
    $entity->setOverride('locale:es', 'name', 'Camisa');
    $entity->setOverride('geo:eu.de', 'price', 19.99);

    expect($entity->override('locale:es', 'name'))->toBe('Camisa')
        ->and($entity->hasOverride('locale:es', 'name'))->toBeTrue()
        ->and($entity->hasOverride('locale:es', 'missing'))->toBeFalse();

    $entity->clearOverride('locale:es', 'name');

    expect($entity->hasOverride('locale:es', 'name'))->toBeFalse()
        ->and($entity->override('geo:eu.de', 'price'))->toBe(19.99);
});

it('HasScopes trait method signatures match the interface parameter names exactly', function (): void {
    $reflection = new ReflectionClass(TraitProduct::class);

    $setOverrideParam = $reflection->getMethod('setOverride')->getParameters()[0];
    $overrideParam = $reflection->getMethod('override')->getParameters()[0];
    $hasOverrideParam = $reflection->getMethod('hasOverride')->getParameters()[0];
    $clearOverrideParam = $reflection->getMethod('clearOverride')->getParameters()[0];

    expect($setOverrideParam->getName())->toBe('signature')
        ->and($overrideParam->getName())->toBe('signature')
        ->and($hasOverrideParam->getName())->toBe('signature')
        ->and($clearOverrideParam->getName())->toBe('signature');
});
