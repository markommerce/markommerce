<?php

declare(strict_types=1);

use Markommerce\Config\Attributes\Config;
use Markommerce\Config\Exceptions\ConfigKeyConflictException;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Exceptions\InvalidConfigClassException;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\ValueObjects\ConfigDefinition;

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
        private readonly string $requiredParam, // @phpstan-ignore property.onlyWritten
    ) {}

    #[Config(key: 'test/ctor.value')]
    public int $value = 1;
}

class OptionalConstructorConfig
{
    public function __construct(
        private string $optionalParam = 'default', // @phpstan-ignore property.onlyWritten
    ) {}

    #[Config(key: 'test/optctor.value')]
    public int $value = 1;
}

class ScopedWithAttributeConfig
{
    // Note: any #[Scoped] attribute was removed as config no longer depends on markommerce/scope
    #[Config(key: 'test/scoped-attr.value')]
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

class SecretConfig
{
    #[Config(key: 'test/secret.value', secret: true)]
    public string $value = '';
}

it(
    'it constructs ConfigRegistryBuilder with no constructor parameters and exposes a single-argument build(configClasses) method',
    function (): void {
        $builder = new ConfigRegistryBuilder();

        expect($builder)->toBeInstanceOf(ConfigRegistryBuilder::class);

        $reflection = new ReflectionClass(ConfigRegistryBuilder::class);
        $buildMethod = $reflection->getMethod('build');
        $params = $buildMethod->getParameters();

        expect($params)->toHaveCount(1)
            ->and($params[0]->getName())->toBe('configClasses');
    },
);

it(
    'it builds a ConfigRegistry from a list of #[Config]-annotated classes without consulting any scope registry',
    function (): void {
        $builder = new ConfigRegistryBuilder();
        $registry = $builder->build([SinglePropConfig::class]);

        expect($registry)->toBeInstanceOf(ConfigRegistry::class);

        $definition = $registry->definition(SinglePropConfig::class, 'value');

        expect($definition)->toBeInstanceOf(ConfigDefinition::class)
            ->and($definition->key)->toBe('test/general.value')
            ->and($definition->configClass)->toBe(SinglePropConfig::class)
            ->and($definition->field)->toBe('value');
    },
);

it(
    'it ignores any #[Scoped] attributes on properties when building the registry (no axes captured)',
    function (): void {
        $builder = new ConfigRegistryBuilder();
        $registry = $builder->build([ScopedWithAttributeConfig::class]);

        $definition = $registry->definition(ScopedWithAttributeConfig::class, 'value');

        // ConfigDefinition no longer has axes — just verify the definition was built fine
        expect($definition)->toBeInstanceOf(ConfigDefinition::class)
            ->and($definition->key)->toBe('test/scoped-attr.value');
    },
);

it(
    'it throws ConfigKeyConflictException unchanged when two properties declare the same #[Config(key)]',
    function (): void {
        $builder = new ConfigRegistryBuilder();

        expect(fn () => $builder->build([ConflictConfigA::class, ConflictConfigB::class]))
            ->toThrow(ConfigKeyConflictException::class);
    },
);

it(
    'it throws InvalidConfigClassException unchanged when a #[Config] property has a union type',
    function (): void {
        $builder = new ConfigRegistryBuilder();

        expect(fn () => $builder->build([UnionTypeConfig::class]))
            ->toThrow(InvalidConfigClassException::class);
    },
);

it('builds a registry from multiple config classes', function (): void {
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([SinglePropConfig::class, AnotherConfig::class]);

    $all = $registry->all();

    expect($all)->toHaveCount(2);
});

it('captures the property\'s declared PHP type as a normalized string in ConfigDefinition', function (): void {
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([SinglePropConfig::class]);

    $definition = $registry->definition(SinglePropConfig::class, 'value');

    expect($definition->type)->toBe('int');
});

it('captures the property\'s default value in ConfigDefinition', function (): void {
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([SinglePropConfig::class]);

    $definition = $registry->definition(SinglePropConfig::class, 'value');

    expect($definition->defaultValue)->toBe(42);
});

it(
    'throws InvalidConfigClassException when a #[Config] property is non-nullable and has no default value',
    function (): void {
        $builder = new ConfigRegistryBuilder();

        expect(fn () => $builder->build([NonNullableNoDefaultConfig::class]))
            ->toThrow(InvalidConfigClassException::class);
    },
);

it(
    'throws InvalidConfigClassException when the config class declares a constructor with at least one required parameter',
    function (): void {
        $builder = new ConfigRegistryBuilder();

        expect(fn () => $builder->build([RequiredConstructorConfig::class]))
            ->toThrow(InvalidConfigClassException::class);
    },
);

it('accepts a config class whose constructor has only optional/defaulted parameters', function (): void {
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([OptionalConstructorConfig::class]);

    expect($registry)->toBeInstanceOf(ConfigRegistry::class);
});

it(
    'throws ConfigNotFoundException when registry.definition(class, field) is called for unknown fields',
    function (): void {
        $builder = new ConfigRegistryBuilder();
        $registry = $builder->build([SinglePropConfig::class]);

        expect(fn () => $registry->definition(SinglePropConfig::class, 'nonexistent'))
            ->toThrow(ConfigNotFoundException::class);
    },
);

it('returns a definition by string key via registry.byKey(key)', function (): void {
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([SinglePropConfig::class]);

    $definition = $registry->byKey('test/general.value');

    expect($definition)->toBeInstanceOf(ConfigDefinition::class)
        ->and($definition->key)->toBe('test/general.value');
});

it(
    'it does not import scope namespace from any of the three production files after task completes',
    function (): void {
        $files = [
            dirname(__DIR__, 3) . '/src/ConfigResolver.php',
            dirname(__DIR__, 3) . '/src/Cache/CachingConfigResolver.php',
            dirname(__DIR__, 3) . '/src/Registry/ConfigRegistryBuilder.php',
        ];

        $scopeNs = 'Markommerce' . '\\' . 'Scope' . '\\';

        foreach ($files as $file) {
            $content = file_get_contents($file);
            expect($content)->not->toContain($scopeNs, "File $file still imports scope namespace");
        }
    },
);
