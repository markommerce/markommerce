<?php

declare(strict_types=1);

use Marko\Core\Container\PreferenceRegistry;
use Markommerce\Config\Attributes\Config;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Exceptions\InvalidConfigValueException;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Resolution\OverrideMatcher;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Config\Tests\Fakes\FakeScopeRegistry;
use Markommerce\Config\ValueObjects\ConfigRow;
use Markommerce\Scope\Attributes\Scoped;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

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

class ResolverScopedConfig
{
    #[Config(key: 'resolver/scoped.value')]
    #[Scoped(axes: ['store'])]
    public string $value = 'scoped-default';
}

class ResolverMultiAxisConfig
{
    #[Config(key: 'resolver/multi.value')]
    #[Scoped(axes: ['channel', 'locale'])]
    public string $value = 'multi-default';
}

// ---- Helpers ----

function makeConfigResolver(
    ConfigRegistry $registry,
    InMemoryConfigStorage $storage,
    ScopeContext $context,
): ConfigResolver {
    $scopeRegistry = new FakeScopeRegistry(['store', 'channel', 'locale']);
    $enumerator = new SignatureCandidateEnumerator($scopeRegistry);
    $overrideMatcher = new OverrideMatcher($enumerator);
    $valueCaster = new ValueCaster();
    $secretCipher = new NullSecretCipher();
    $proxyLocator = new ProxyLocator();
    $preferenceRegistry = new PreferenceRegistry();

    return new ConfigResolver(
        configRegistry: $registry,
        configStorage: $storage,
        overrideMatcher: $overrideMatcher,
        valueCaster: $valueCaster,
        scopeContext: $context,
        secretCipher: $secretCipher,
        proxyLocator: $proxyLocator,
        preferenceRegistry: $preferenceRegistry,
    );
}

/**
 * Build a ScopeRegistryInterface with hierarchical paths per axis.
 *
 * @param array<string, list<string>> $axes axis name => ordered list of paths (first = default)
 */
function makeScopeRegistryWithPaths(array $axes): ScopeRegistryInterface
{
    return new class ($axes) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        /** @param array<string, list<string>> $axes */
        public function __construct(array $axes)
        {
            $this->builtAxes = [];

            foreach ($axes as $name => $paths) {
                $default = $paths[0] ?? 'default';
                $hierarchy = new ScopeHierarchy($paths);
                $this->builtAxes[$name] = new ScopeAxis(name: $name, hierarchy: $hierarchy, default: $default);
            }
        }

        public function hasAxis(string $name): bool
        {
            return isset($this->builtAxes[$name]);
        }

        /** @throws UnknownAxisException */
        public function getAxis(string $name): ScopeAxis
        {
            if (!isset($this->builtAxes[$name])) {
                throw UnknownAxisException::forAxis($name);
            }

            return $this->builtAxes[$name];
        }

        /** @return list<string> */
        public function listAxes(): array
        {
            return array_keys($this->builtAxes);
        }

        /** @throws UnknownAxisException */
        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            return $this->getAxis($axisName)->hierarchy;
        }
    };
}

function makeScopeContext(): ScopeContext
{
    return new ScopeContext(new FakeScopeRegistry(['store', 'channel', 'locale']));
}

// ---- Tests ----

it('returns the property default when no row exists for the config key', function (): void {
    $fakeScopeRegistry = new FakeScopeRegistry();
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([ResolverIntConfig::class], $fakeScopeRegistry);
    $storage = new InMemoryConfigStorage();
    $context = makeScopeContext();

    $resolver = makeConfigResolver($registry, $storage, $context);

    $result = $resolver->resolved(ResolverIntConfig::class, 'intValue');

    expect($result)->toBe(99);
});

it(
    'returns the global value cast to the declared type when the row has a global but no matching overrides',
    function (): void {
        $fakeScopeRegistry = new FakeScopeRegistry();
        $builder = new ConfigRegistryBuilder();
        $registry = $builder->build([ResolverIntConfig::class], $fakeScopeRegistry);
        $storage = new InMemoryConfigStorage();
        $context = makeScopeContext();
    
        // Seed storage with a global value and no overrides
    $storage->compareAndSave('resolver/test.intValue', new ConfigRow(
            key: 'resolver/test.intValue',
            value: 42,
            overrides: [],
            version: 0,
        ), 0);
    
        $resolver = makeConfigResolver($registry, $storage, $context);
    
        $result = $resolver->resolved(ResolverIntConfig::class, 'intValue');
    
        expect($result)->toBe(42);
    }
);

it(
    'returns the override value when a matching signature exists in the row for the current ScopeContext',
    function (): void {
        // Use a scope registry with a non-default path so the override is not filtered out
    $scopeRegistry = makeScopeRegistryWithPaths(['store' => ['default', 'eu', 'eu.de']]);
        $builder = new ConfigRegistryBuilder();
        $registry = $builder->build([ResolverScopedConfig::class], $scopeRegistry);
        $storage = new InMemoryConfigStorage();
    
        $context = new ScopeContext($scopeRegistry);
        $context->in('store', 'eu');
    
        // Seed row with a global value and a store:eu override
    $storage->compareAndSave('resolver/scoped.value', new ConfigRow(
            key: 'resolver/scoped.value',
            value: 'global-value',
            overrides: ['store:eu' => 'override-for-eu'],
            version: 0,
        ), 0);
    
        $enumerator = new SignatureCandidateEnumerator($scopeRegistry);
        $overrideMatcher = new OverrideMatcher($enumerator);
        $resolver = new ConfigResolver(
            configRegistry: $registry,
            configStorage: $storage,
            overrideMatcher: $overrideMatcher,
            valueCaster: new ValueCaster(),
            scopeContext: $context,
            secretCipher: new NullSecretCipher(),
            proxyLocator: new ProxyLocator(),
            preferenceRegistry: new PreferenceRegistry(),
        );
    
        $result = $resolver->resolved(ResolverScopedConfig::class, 'value');
    
        expect($result)->toBe('override-for-eu');
    }
);

it('falls back from override to global when no override matches the current ScopeContext', function (): void {
    $scopeRegistry = makeScopeRegistryWithPaths(['store' => ['default', 'eu', 'eu.de']]);
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([ResolverScopedConfig::class], $scopeRegistry);
    $storage = new InMemoryConfigStorage();

    // Context is at eu.de, but the only override is for eu.fr (no match)
    $context = new ScopeContext($scopeRegistry);
    $context->in('store', 'eu');

    // Seed row: global value exists, but override is for a different scope
    $storage->compareAndSave('resolver/scoped.value', new ConfigRow(
        key: 'resolver/scoped.value',
        value: 'global-value',
        overrides: ['store:eu.de' => 'override-for-eu-de'],
        version: 0,
    ), 0);

    $enumerator = new SignatureCandidateEnumerator($scopeRegistry);
    $overrideMatcher = new OverrideMatcher($enumerator);
    $resolver = new ConfigResolver(
        configRegistry: $registry,
        configStorage: $storage,
        overrideMatcher: $overrideMatcher,
        valueCaster: new ValueCaster(),
        scopeContext: $context,
        secretCipher: new NullSecretCipher(),
        proxyLocator: new ProxyLocator(),
        preferenceRegistry: new PreferenceRegistry(),
    );

    $result = $resolver->resolved(ResolverScopedConfig::class, 'value');

    expect($result)->toBe('global-value');
});

it('falls back from global to default when the global is null and no override matches', function (): void {
    $fakeScopeRegistry = new FakeScopeRegistry();
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([ResolverIntConfig::class], $fakeScopeRegistry);
    $storage = new InMemoryConfigStorage();
    $context = makeScopeContext();

    // Seed a row with null global value and no overrides
    $storage->compareAndSave('resolver/test.intValue', new ConfigRow(
        key: 'resolver/test.intValue',
        value: null,
        overrides: [],
        version: 0,
    ), 0);

    $resolver = makeConfigResolver($registry, $storage, $context);

    $result = $resolver->resolved(ResolverIntConfig::class, 'intValue');

    expect($result)->toBe(99);
});

it(
    'preserves the most-specific override priority via OverrideMatcher when both single-axis and composite overrides are present',
    function (): void {
        // 'default' is the default for channel, 'en' is the default for locale
    // so 'b2b' and 'fr' are non-default paths that won't be filtered out
    $scopeRegistry = makeScopeRegistryWithPaths([
            'channel' => ['default', 'b2b', 'b2c'],
            'locale'  => ['en', 'fr', 'fr.be'],
        ]);
        $builder = new ConfigRegistryBuilder();
        $registry = $builder->build([ResolverMultiAxisConfig::class], $scopeRegistry);
        $storage = new InMemoryConfigStorage();
    
        $context = new ScopeContext($scopeRegistry);
        $context->in('channel', 'b2b')->in('locale', 'fr');
    
        // Store overrides: single-axis + composite — composite should win
    $storage->compareAndSave('resolver/multi.value', new ConfigRow(
            key: 'resolver/multi.value',
            value: 'global-value',
            overrides: [
                'channel:b2b'        => 'b2b-only',
                'locale:fr'          => 'fr-only',
                'channel:b2b|locale:fr' => 'composite-b2b-fr',
            ],
            version: 0,
        ), 0);
    
        $enumerator = new SignatureCandidateEnumerator($scopeRegistry);
        $overrideMatcher = new OverrideMatcher($enumerator);
        $resolver = new ConfigResolver(
            configRegistry: $registry,
            configStorage: $storage,
            overrideMatcher: $overrideMatcher,
            valueCaster: new ValueCaster(),
            scopeContext: $context,
            secretCipher: new NullSecretCipher(),
            proxyLocator: new ProxyLocator(),
            preferenceRegistry: new PreferenceRegistry(),
        );
    
        $result = $resolver->resolved(ResolverMultiAxisConfig::class, 'value');
    
        expect($result)->toBe('composite-b2b-fr');
    }
);

it('casts stored ints to int and stored strings to string', function (): void {
    $fakeScopeRegistry = new FakeScopeRegistry();
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([ResolverIntConfig::class, ResolverStringConfig::class], $fakeScopeRegistry);
    $storage = new InMemoryConfigStorage();
    $context = makeScopeContext();

    // Seed an int value for the int config
    $storage->compareAndSave('resolver/test.intValue', new ConfigRow(
        key: 'resolver/test.intValue',
        value: 77,
        overrides: [],
        version: 0,
    ), 0);

    // Seed a string value for the string config
    $storage->compareAndSave('resolver/test.stringValue', new ConfigRow(
        key: 'resolver/test.stringValue',
        value: 'hello',
        overrides: [],
        version: 0,
    ), 0);

    $resolver = makeConfigResolver($registry, $storage, $context);

    $intResult = $resolver->resolved(ResolverIntConfig::class, 'intValue');
    $stringResult = $resolver->resolved(ResolverStringConfig::class, 'stringValue');

    expect($intResult)->toBe(77)
        ->and($stringResult)->toBe('hello');
});

it('propagates InvalidConfigValueException unchanged from ValueCaster', function (): void {
    $fakeScopeRegistry = new FakeScopeRegistry();
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([ResolverIntConfig::class], $fakeScopeRegistry);
    $storage = new InMemoryConfigStorage();
    $context = makeScopeContext();

    // Seed an int config with a string value — this will fail during cast
    $storage->compareAndSave('resolver/test.intValue', new ConfigRow(
        key: 'resolver/test.intValue',
        value: 'not-an-int',
        overrides: [],
        version: 0,
    ), 0);

    $resolver = makeConfigResolver($registry, $storage, $context);

    expect(fn () => $resolver->resolved(ResolverIntConfig::class, 'intValue'))
        ->toThrow(InvalidConfigValueException::class);
});

it('throws ConfigNotFoundException when the configClass + field pair is not in the ConfigRegistry', function (): void {
    $fakeScopeRegistry = new FakeScopeRegistry();
    $builder = new ConfigRegistryBuilder();
    // Build registry with ResolverIntConfig, but try to resolve ResolverStringConfig (not registered)
    $registry = $builder->build([ResolverIntConfig::class], $fakeScopeRegistry);
    $storage = new InMemoryConfigStorage();
    $context = makeScopeContext();

    $resolver = makeConfigResolver($registry, $storage, $context);

    expect(fn () => $resolver->resolved(ResolverStringConfig::class, 'stringValue'))
        ->toThrow(ConfigNotFoundException::class);
});

it(
    'resolves under a synthetic ScopeContext passed to resolvedAt without reading from the injected live context',
    function (): void {
        $scopeRegistry = makeScopeRegistryWithPaths(['store' => ['default', 'eu', 'eu.de']]);
        $builder = new ConfigRegistryBuilder();
        $registry = $builder->build([ResolverScopedConfig::class], $scopeRegistry);
        $storage = new InMemoryConfigStorage();
    
        // The live (injected) context has no active axes
    $liveContext = new ScopeContext($scopeRegistry);
    
        // Seed a row with a store:eu override
    $storage->compareAndSave('resolver/scoped.value', new ConfigRow(
            key: 'resolver/scoped.value',
            value: 'global-value',
            overrides: ['store:eu' => 'override-for-eu'],
            version: 0,
        ), 0);
    
        $enumerator = new SignatureCandidateEnumerator($scopeRegistry);
        $overrideMatcher = new OverrideMatcher($enumerator);
        $resolver = new ConfigResolver(
            configRegistry: $registry,
            configStorage: $storage,
            overrideMatcher: $overrideMatcher,
            valueCaster: new ValueCaster(),
            scopeContext: $liveContext,
            secretCipher: new NullSecretCipher(),
            proxyLocator: new ProxyLocator(),
            preferenceRegistry: new PreferenceRegistry(),
        );
    
        // Build an explicit context at store:eu
    $explicitContext = new ScopeContext($scopeRegistry);
        $explicitContext->in('store', 'eu');
    
        $resultViaResolvedAt = $resolver->resolvedAt(ResolverScopedConfig::class, 'value', $explicitContext);
        // resolved() uses the live context (no store set) — so no override matches, falls back to global
    $resultViaResolved = $resolver->resolved(ResolverScopedConfig::class, 'value');
    
        expect($resultViaResolvedAt)->toBe('override-for-eu')
            ->and($resultViaResolved)->toBe('global-value');
    }
);

it('does not mutate the injected ScopeContext when resolvedAt is called', function (): void {
    $scopeRegistry = makeScopeRegistryWithPaths(['store' => ['default', 'eu', 'eu.de']]);
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([ResolverScopedConfig::class], $scopeRegistry);
    $storage = new InMemoryConfigStorage();

    // Live context has store set to eu.de
    $liveContext = new ScopeContext($scopeRegistry);
    $liveContext->in('store', 'eu.de');

    $storage->compareAndSave('resolver/scoped.value', new ConfigRow(
        key: 'resolver/scoped.value',
        value: 'global-value',
        overrides: ['store:eu' => 'override-for-eu'],
        version: 0,
    ), 0);

    $enumerator = new SignatureCandidateEnumerator($scopeRegistry);
    $overrideMatcher = new OverrideMatcher($enumerator);
    $resolver = new ConfigResolver(
        configRegistry: $registry,
        configStorage: $storage,
        overrideMatcher: $overrideMatcher,
        valueCaster: new ValueCaster(),
        scopeContext: $liveContext,
        secretCipher: new NullSecretCipher(),
        proxyLocator: new ProxyLocator(),
        preferenceRegistry: new PreferenceRegistry(),
    );

    // Snapshot live state before calling resolvedAt
    $stateBefore = $liveContext->state();

    // Call resolvedAt with a different explicit context
    $explicitContext = new ScopeContext($scopeRegistry);
    $explicitContext->in('store', 'eu');
    $resolver->resolvedAt(ResolverScopedConfig::class, 'value', $explicitContext);

    // Live context must not have changed
    expect($liveContext->state())->toBe($stateBefore);
});
