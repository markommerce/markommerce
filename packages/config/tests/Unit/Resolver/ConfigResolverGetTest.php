<?php

declare(strict_types=1);

use Marko\Core\Container\PreferenceRegistry;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Exceptions\InvalidConfigClassException;
use Markommerce\Config\Exceptions\ProxyNotGeneratedException;
use Markommerce\Config\Proxy\ProxyAutoloader;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Resolution\OverrideMatcher;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Config\Tests\Fakes\FakeScopeRegistry;
use Markommerce\Config\Tests\Unit\Resolver\Fixtures\ExtendedSampleConfig;
use Markommerce\Config\Tests\Unit\Resolver\Fixtures\SampleConfig;
use Markommerce\Config\Tests\Unit\Resolver\Fixtures\UnrelatedConfig;
use Markommerce\Config\ValueObjects\ConfigRow;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

// Register the autoloader for generated fixture proxies
$fixturesGeneratedDir = __DIR__ . '/Fixtures/Generated';
$autoloader = new ProxyAutoloader($fixturesGeneratedDir);
$autoloader->register();

// ---- Helpers ----

function makeGetTestResolver(
    array $configClasses = [],
    ?InMemoryConfigStorage $storage = null,
    ?PreferenceRegistry $preferenceRegistry = null,
): ConfigResolver {
    $fakeScopeRegistry = new FakeScopeRegistry();
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build($configClasses, $fakeScopeRegistry);
    $storage ??= new InMemoryConfigStorage();
    $enumerator = new SignatureCandidateEnumerator($fakeScopeRegistry);
    $overrideMatcher = new OverrideMatcher($enumerator);
    $valueCaster = new ValueCaster();
    $secretCipher = new NullSecretCipher();
    $proxyLocator = new ProxyLocator();
    $preferenceRegistry ??= new PreferenceRegistry();
    $scopeContext = new ScopeContext($fakeScopeRegistry);

    return new ConfigResolver(
        configRegistry: $registry,
        configStorage: $storage,
        overrideMatcher: $overrideMatcher,
        valueCaster: $valueCaster,
        scopeContext: $scopeContext,
        secretCipher: $secretCipher,
        proxyLocator: $proxyLocator,
        preferenceRegistry: $preferenceRegistry,
    );
}

// ---- Tests ----

it('returns an instance of the generated proxy class for a non-preferenced config class', function (): void {
    $resolver = makeGetTestResolver([SampleConfig::class]);

    $proxy = $resolver->get(SampleConfig::class);

    expect($proxy)->toBeInstanceOf(
        'Markommerce\\Config\\Generated\\Markommerce\\Config\\Tests\\Unit\\Resolver\\Fixtures\\SampleConfig_Resolved',
    );
});

it(
    'returns an instance of the preferred proxy class when a Preference is registered via PreferenceRegistry',
    function (): void {
        $preferenceRegistry = new PreferenceRegistry();
        $preferenceRegistry->register(SampleConfig::class, ExtendedSampleConfig::class);
    
        $resolver = makeGetTestResolver([SampleConfig::class], preferenceRegistry: $preferenceRegistry);
    
        $proxy = $resolver->get(SampleConfig::class);
    
        expect($proxy)->toBeInstanceOf(ExtendedSampleConfig::class);
        expect($proxy)->toBeInstanceOf(
            'Markommerce\\Config\\Generated\\Markommerce\\Config\\Tests\\Unit\\Resolver\\Fixtures\\ExtendedSampleConfig_Resolved',
        );
    }
);

it('instantiates the proxy with the resolver as the __resolver dependency', function (): void {
    $resolver = makeGetTestResolver([SampleConfig::class]);

    $proxy = $resolver->get(SampleConfig::class);
    $reflection = new ReflectionProperty($proxy, '__resolver');
    $injectedResolver = $reflection->getValue($proxy);

    expect($injectedResolver)->toBe($resolver);
});

it(
    'throws ProxyNotGeneratedException with a config:generate suggestion when the proxy class is missing',
    function (): void {
        // Use a class whose proxy has not been generated (not registered with our autoloader)
    $resolver = makeGetTestResolver([UnrelatedConfig::class]);
    
        expect(fn () => $resolver->get(UnrelatedConfig::class))
            ->toThrow(ProxyNotGeneratedException::class);
    }
);

it(
    'throws InvalidConfigClassException when the preferred class is not a subclass of the requested config class',
    function (): void {
        $preferenceRegistry = new PreferenceRegistry();
        // Register UnrelatedConfig as a preference for SampleConfig — it does NOT extend SampleConfig
    $preferenceRegistry->register(SampleConfig::class, UnrelatedConfig::class);
    
        $resolver = makeGetTestResolver(
            [SampleConfig::class, UnrelatedConfig::class],
            preferenceRegistry: $preferenceRegistry
        );
    
        expect(fn () => $resolver->get(SampleConfig::class))
            ->toThrow(InvalidConfigClassException::class);
    }
);

it(
    'returns instances whose property hooks delegate through to ConfigResolver::resolved() for each field',
    function (): void {
        $storage = new InMemoryConfigStorage();
        $storage->compareAndSave('test/greeting', new ConfigRow(
            key: 'test/greeting',
            value: 'Stored greeting',
            overrides: [],
            version: 0,
        ), 0);
    
        $resolver = makeGetTestResolver([SampleConfig::class], $storage);
    
        $proxy = $resolver->get(SampleConfig::class);
    
        expect($proxy->greeting)->toBe('Stored greeting');
    }
);
