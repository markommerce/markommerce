<?php

declare(strict_types=1);

use Marko\Core\Container\PreferenceRegistry;
use Markommerce\Config\Attributes\Config;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Config\ValueObjects\ConfigRow;

// ---- Fixture config classes ----

class ResolverIntConfig
{
    #[Config(key: 'resolver/test.intValue')]
    public int $intValue = 99;
}

class ResolverStringConfig
{
    #[Config(key: 'resolver/test.stringValue')]
    public string $stringValue = 'default-string';
}

// ---- Helpers ----

function makeConfigResolver(
    ConfigRegistry $registry,
    InMemoryConfigStorage $storage,
): ConfigResolver {
    $valueCaster = new ValueCaster();
    $secretCipher = new NullSecretCipher();
    $proxyLocator = new ProxyLocator();
    $preferenceRegistry = new PreferenceRegistry();

    return new ConfigResolver(
        configRegistry: $registry,
        configStorage: $storage,
        valueCaster: $valueCaster,
        secretCipher: $secretCipher,
        proxyLocator: $proxyLocator,
        preferenceRegistry: $preferenceRegistry,
    );
}

// ---- Tests ----

it(
    'constructs ConfigResolver with seven dependencies: registry, storage, valueCaster, secretCipher, proxyLocator, preferenceRegistry',
    function (): void {
        $builder = new ConfigRegistryBuilder();
        $registry = $builder->build([ResolverIntConfig::class]);
        $storage = new InMemoryConfigStorage();

        $resolver = new ConfigResolver(
            configRegistry: $registry,
            configStorage: $storage,
            valueCaster: new ValueCaster(),
            secretCipher: new NullSecretCipher(),
            proxyLocator: new ProxyLocator(),
            preferenceRegistry: new PreferenceRegistry(),
        );

        expect($resolver)->toBeInstanceOf(ConfigResolver::class);
    },
);

it('it does not expose a resolvedAt method on the ConfigResolver class after task completes', function (): void {
    expect(method_exists(ConfigResolver::class, 'resolvedAt'))->toBeFalse();
});

it(
    'it returns the property default value when ConfigResolver resolved is called and no row exists for the key',
    function (): void {
        $builder = new ConfigRegistryBuilder();
        $registry = $builder->build([ResolverIntConfig::class]);
        $storage = new InMemoryConfigStorage();

        $resolver = makeConfigResolver($registry, $storage);

        $result = $resolver->resolved(ResolverIntConfig::class, 'intValue');

        expect($result)->toBe(99);
    },
);

it(
    'it returns the row global value cast to the declared type when ConfigResolver resolved finds a row with a non-null value',
    function (): void {
        $builder = new ConfigRegistryBuilder();
        $registry = $builder->build([ResolverIntConfig::class]);
        $storage = new InMemoryConfigStorage();

        $storage->compareAndSave('resolver/test.intValue', new ConfigRow(
            key: 'resolver/test.intValue',
            value: 42,
            version: 0,
        ), 0);

        $resolver = makeConfigResolver($registry, $storage);

        $result = $resolver->resolved(ResolverIntConfig::class, 'intValue');

        expect($result)->toBe(42);
    },
);

it(
    'it returns the property default value when ConfigResolver resolved finds a row with value=null',
    function (): void {
        $builder = new ConfigRegistryBuilder();
        $registry = $builder->build([ResolverIntConfig::class]);
        $storage = new InMemoryConfigStorage();

        $storage->compareAndSave('resolver/test.intValue', new ConfigRow(
            key: 'resolver/test.intValue',
            value: null,
            version: 0,
        ), 0);

        $resolver = makeConfigResolver($registry, $storage);

        $result = $resolver->resolved(ResolverIntConfig::class, 'intValue');

        expect($result)->toBe(99);
    },
);

it(
    'it throws ConfigNotFoundException when ConfigResolver resolved is called for a class/field pair not in the registry',
    function (): void {
        $builder = new ConfigRegistryBuilder();
        $registry = $builder->build([ResolverIntConfig::class]);
        $storage = new InMemoryConfigStorage();

        $resolver = makeConfigResolver($registry, $storage);

        expect(fn () => $resolver->resolved(ResolverStringConfig::class, 'stringValue'))
            ->toThrow(ConfigNotFoundException::class);
    },
);

it(
    'it declares ConfigResolver\'s constructor-promoted properties (configRegistry, configStorage, valueCaster, secretCipher, proxyLocator, preferenceRegistry) with protected visibility (verified via reflection)',
    function (): void {
        $reflection = new ReflectionClass(ConfigResolver::class);

        $expectedProtected = [
            'configRegistry',
            'configStorage',
            'valueCaster',
            'secretCipher',
            'proxyLocator',
            'preferenceRegistry',
        ];

        foreach ($expectedProtected as $propertyName) {
            $property = $reflection->getProperty($propertyName);
            expect($property->isProtected())->toBeTrue(
                "Expected property '$propertyName' to be protected",
            );
        }
    },
);
