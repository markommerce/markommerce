<?php

declare(strict_types=1);

use Markommerce\Config\Attributes\Config;
use Markommerce\Config\Exceptions\AxisNotDeclaredException;
use Markommerce\Config\Exceptions\ConfigKeyConflictException;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Exceptions\InvalidConfigClassException;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Tests\Fakes\FakeScopeRegistry;
use Markommerce\Config\ValueObjects\ConfigDefinition;
use Markommerce\Scope\Attributes\Scoped;

// --- Fixture config classes for testing ---

class SinglePropConfig
{
    #[Config(key: 'test/general.value')]
    public int $value = 42;
}

class AnotherConfig
{
    #[Config(key: 'test/general.name')]
    public string $name = 'default';
}

class MultiPropConfig
{
    #[Config(key: 'multi/general.one')]
    public int $one = 1;

    #[Config(key: 'multi/general.two')]
    public string $two = 'hello';
}

class UnionTypeConfig
{
    #[Config(key: 'test/union.value')]
    public int|string $value = 42;
}

class IntersectionTypeConfig
{
    // We can't easily declare intersection type at runtime for a property
    // but we can test with a union which is the practical case
}

class NonNullableNoDefaultConfig
{
    #[Config(key: 'test/nodft.value')]
    public int $value;
}

class NullableNoDefaultConfig
{
    #[Config(key: 'test/nullable.value')]
    public ?int $value;
}

class RequiredConstructorConfig
{
    public function __construct(
        private readonly string $requiredParam,
    ) {}

    #[Config(key: 'test/ctor.value')]
    public int $value = 1;
}

class OptionalConstructorConfig
{
    public function __construct(
        private string $optionalParam = 'default',
    ) {}

    #[Config(key: 'test/optctor.value')]
    public int $value = 1;
}

class ScopedConfig
{
    #[Config(key: 'test/scoped.value')]
    #[Scoped(axes: ['website', 'store'])]
    public int $value = 10;
}

class UnscopedConfig
{
    #[Config(key: 'test/unscoped.value')]
    public int $value = 10;
}

class ConflictConfigA
{
    #[Config(key: 'test/conflict.key')]
    public int $value = 1;
}

class ConflictConfigB
{
    #[Config(key: 'test/conflict.key')]
    public int $other = 2;
}

class ScopedWithUnknownAxisConfig
{
    #[Config(key: 'test/unknownaxis.value')]
    #[Scoped(axes: ['nonexistent'])]
    public int $value = 10;
}

class SecretConfig
{
    #[Config(key: 'test/secret.value', secret: true)]
    public string $value = '';
}

it('builds a registry from a single config class with one #[Config] property', function (): void {
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([SinglePropConfig::class], new FakeScopeRegistry());

    expect($registry)->toBeInstanceOf(ConfigRegistry::class);

    $definition = $registry->definition(SinglePropConfig::class, 'value');

    expect($definition)->toBeInstanceOf(ConfigDefinition::class)
        ->and($definition->key)->toBe('test/general.value')
        ->and($definition->configClass)->toBe(SinglePropConfig::class)
        ->and($definition->field)->toBe('value');
});

it('builds a registry from multiple config classes', function (): void {
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([SinglePropConfig::class, AnotherConfig::class], new FakeScopeRegistry());

    $all = $registry->all();

    expect($all)->toHaveCount(2);
});

it('captures the property\'s declared PHP type as a normalized string in ConfigDefinition', function (): void {
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([SinglePropConfig::class], new FakeScopeRegistry());

    $definition = $registry->definition(SinglePropConfig::class, 'value');

    expect($definition->type)->toBe('int');
});

it('captures the property\'s default value in ConfigDefinition', function (): void {
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([SinglePropConfig::class], new FakeScopeRegistry());

    $definition = $registry->definition(SinglePropConfig::class, 'value');

    expect($definition->defaultValue)->toBe(42);
});

it('captures axes from #[Scoped] when present and uses an empty axes list when absent', function (): void {
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build(
        [ScopedConfig::class, UnscopedConfig::class],
        new FakeScopeRegistry(['website', 'store']),
    );

    $scoped = $registry->definition(ScopedConfig::class, 'value');
    $unscoped = $registry->definition(UnscopedConfig::class, 'value');

    expect($scoped->axes)->toBe(['website', 'store'])
        ->and($unscoped->axes)->toBe([]);
});

it('throws ConfigKeyConflictException when two properties declare the same #[Config(key)]', function (): void {
    $builder = new ConfigRegistryBuilder();

    expect(fn () => $builder->build([ConflictConfigA::class, ConflictConfigB::class], new FakeScopeRegistry()))
        ->toThrow(ConfigKeyConflictException::class);
});

it('throws AxisNotDeclaredException when a property\'s #[Scoped] axis is not in the ScopeRegistry', function (): void {
    $builder = new ConfigRegistryBuilder();

    expect(fn () => $builder->build([ScopedWithUnknownAxisConfig::class], new FakeScopeRegistry()))
        ->toThrow(AxisNotDeclaredException::class);
});

it('throws InvalidConfigClassException when a #[Config] property has a union or intersection type', function (): void {
    $builder = new ConfigRegistryBuilder();

    expect(fn () => $builder->build([UnionTypeConfig::class], new FakeScopeRegistry()))
        ->toThrow(InvalidConfigClassException::class);
});

it(
    'throws InvalidConfigClassException when a #[Config] property is non-nullable and has no default value',
    function (): void {
        $builder = new ConfigRegistryBuilder();

        expect(fn () => $builder->build([NonNullableNoDefaultConfig::class], new FakeScopeRegistry()))
            ->toThrow(InvalidConfigClassException::class);
    },
);

it(
    'throws InvalidConfigClassException when the config class declares a constructor with at least one required parameter',
    function (): void {
        $builder = new ConfigRegistryBuilder();

        expect(fn () => $builder->build([RequiredConstructorConfig::class], new FakeScopeRegistry()))
            ->toThrow(InvalidConfigClassException::class);
    },
);

it('accepts a config class whose constructor has only optional/defaulted parameters', function (): void {
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([OptionalConstructorConfig::class], new FakeScopeRegistry());

    expect($registry)->toBeInstanceOf(ConfigRegistry::class);
});

it(
    'throws ConfigNotFoundException when registry.definition(class, field) is called for unknown fields',
    function (): void {
        $builder = new ConfigRegistryBuilder();
        $registry = $builder->build([SinglePropConfig::class], new FakeScopeRegistry());

        expect(fn () => $registry->definition(SinglePropConfig::class, 'nonexistent'))
            ->toThrow(ConfigNotFoundException::class);
    },
);

it('returns a definition by string key via registry.byKey(key)', function (): void {
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([SinglePropConfig::class], new FakeScopeRegistry());

    $definition = $registry->byKey('test/general.value');

    expect($definition)->toBeInstanceOf(ConfigDefinition::class)
        ->and($definition->key)->toBe('test/general.value');
});
