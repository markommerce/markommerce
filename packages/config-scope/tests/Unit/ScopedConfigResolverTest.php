<?php

declare(strict_types=1);

use Marko\Core\Attributes\Preference;
use Marko\Core\Container\PreferenceRegistry;
use Markommerce\Config\Attributes\Config;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Contracts\SecretCipherInterface;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Config\ValueObjects\ConfigRow;
use Markommerce\ConfigScope\Resolution\OverrideMatcher;
use Markommerce\ConfigScope\ScopedConfigResolver;
use Markommerce\ConfigScope\Storage\InMemoryScopedConfigStorage;
use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Hierarchy\ScopeHierarchy;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;

// ─── Fixtures ─────────────────────────────────────────────────────────────────

class ScopedResolverIntConfig
{
    #[Config(key: 'scoped-resolver/test.intValue')]
    public int $intValue = 99;
}

class ScopedResolverStringConfig
{
    #[Config(key: 'scoped-resolver/test.stringValue')]
    public string $stringValue = 'default-string';
}

class ScopedResolverSecretConfig
{
    #[Config(key: 'scoped-resolver/test.secretValue', secret: true)]
    public string $secretValue = 'default-secret';
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * @param array<string, list<string>> $axesMap
 * @param array<string, string> $defaults
 */
function makeScopedResolverRegistry(array $axesMap = [], array $defaults = []): ScopeRegistryInterface
{
    return new class ($axesMap, $defaults) implements ScopeRegistryInterface
    {
        /** @var array<string, ScopeAxis> */
        private array $builtAxes;

        /** @param array<string, list<string>> $axesMap @param array<string, string> $defaults */
        public function __construct(
            array $axesMap,
            array $defaults = [],
        )
        {
            $this->builtAxes = [];
            foreach ($axesMap as $name => $paths) {
                $default = $defaults[$name] ?? '__default';
                if (!in_array($default, $paths, true)) {
                    $paths = array_merge([$default], $paths);
                }
                $hierarchy = new ScopeHierarchy($paths);
                $this->builtAxes[$name] = new ScopeAxis(name: $name, hierarchy: $hierarchy, default: $default);
            }
        }

        public function hasAxis(string $name): bool
        {
            return isset($this->builtAxes[$name]);
        }

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

        public function getHierarchy(string $axisName): ScopeHierarchy
        {
            return $this->getAxis($axisName)->hierarchy;
        }
    };
}

/**
 * @param array<string, list<string>> $axesMap axis name => scopes
 * @param list<class-string> $configClasses
 * @param array<string, string> $axisDefaults
 * @param array<string, mixed> $globalValues key => raw value to seed in global storage
 * @param array<string, array<string, mixed>> $scopeOverrides configKey => (signature => value)
 * @param array<string, list<string>> $scopedFields configClass::field => axes (e.g. [ScopedResolverIntConfig::class . '::intValue' => ['locale']])
 * @param-out ScopeContext $outContext receives the injected ScopeContext to allow mutations in test
 */
function makeScopedResolver(
    array $axesMap = [],
    array $configClasses = [],
    array $axisDefaults = [],
    array $globalValues = [],
    array $scopeOverrides = [],
    array $scopedFields = [],
    ?ScopeContext &$outContext = null,
    ?InMemoryScopedConfigStorage &$outScopedStorage = null,
    ?SecretCipherInterface $secretCipher = null,
): ScopedConfigResolver {
    $configClasses = $configClasses ?: [ScopedResolverIntConfig::class];
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build($configClasses);

    $storage = new InMemoryConfigStorage();
    foreach ($globalValues as $key => $value) {
        $storage->compareAndSave($key, new ConfigRow(key: $key, value: $value, version: 0), 0);
    }

    $valueCaster = new ValueCaster();
    $secretCipher ??= new NullSecretCipher();
    $proxyLocator = new ProxyLocator();
    $preferenceRegistry = new PreferenceRegistry();

    $scopeRegistry = makeScopedResolverRegistry($axesMap, $axisDefaults);
    $enumerator = new SignatureCandidateEnumerator($scopeRegistry);
    $overrideMatcher = new OverrideMatcher($enumerator);
    $scopeContext = new ScopeContext($scopeRegistry);
    $outContext = $scopeContext;
    $scopedFieldRegistry = new ScopedFieldRegistry($scopeRegistry);

    // Register any scoped fields
    foreach ($scopedFields as $classAndField => $axes) {
        [$class, $field] = explode('::', $classAndField, 2);
        /** @var class-string $class */
        $scopedFieldRegistry->register($class, $field, $axes);
    }

    $scopedStorage = new InMemoryScopedConfigStorage();
    $outScopedStorage = $scopedStorage;
    foreach ($scopeOverrides as $configKey => $overrides) {
        foreach ($overrides as $signature => $value) {
            $scopedStorage->saveOverride($configKey, $signature, $value);
        }
    }

    return new ScopedConfigResolver(
        configRegistry: $registry,
        configStorage: $storage,
        valueCaster: $valueCaster,
        secretCipher: $secretCipher,
        proxyLocator: $proxyLocator,
        preferenceRegistry: $preferenceRegistry,
        scopedConfigStorage: $scopedStorage,
        overrideMatcher: $overrideMatcher,
        scopeContext: $scopeContext,
        scopedFieldRegistry: $scopedFieldRegistry,
    );
}

// ─── Tests ─────────────────────────────────────────────────────────────────────

it('carries the #[Preference(replaces: ConfigResolver::class)] attribute on ScopedConfigResolver', function (): void {
    $reflection = new ReflectionClass(ScopedConfigResolver::class);
    $attributes = $reflection->getAttributes(Preference::class);

    expect($attributes)->not->toBeEmpty();

    $preference = $attributes[0]->newInstance();

    expect($preference->replaces)->toBe(ConfigResolver::class);
});

it('extends Markommerce\Config\ConfigResolver', function (): void {
    $reflection = new ReflectionClass(ScopedConfigResolver::class);

    expect($reflection->getParentClass()->getName())->toBe(ConfigResolver::class);
});

it(
    'returns the property default value from ScopedConfigResolver resolved when no axes are registered for the field (delegates to parent)',
    function (): void {
        $resolver = makeScopedResolver(
            axesMap: [],
            configClasses: [ScopedResolverIntConfig::class],
        );
    
        $result = $resolver->resolved(ScopedResolverIntConfig::class, 'intValue');
    
        expect($result)->toBe(99);
    }
);

it(
    'returns the global value from ScopedConfigResolver resolved when axes are registered but no overrides exist',
    function (): void {
        $resolver = makeScopedResolver(
            axesMap: ['locale' => ['en', 'fr']],
            configClasses: [ScopedResolverIntConfig::class],
            globalValues: ['scoped-resolver/test.intValue' => 42],
            scopedFields: [ScopedResolverIntConfig::class . '::intValue' => ['locale']],
        );
    
        $result = $resolver->resolved(ScopedResolverIntConfig::class, 'intValue');
    
        expect($result)->toBe(42);
    }
);

it('falls back from override to global when no override matches the current ScopeContext', function (): void {
    $context = null;
    $resolver = makeScopedResolver(
        axesMap: ['locale' => ['en', 'fr']],
        configClasses: [ScopedResolverIntConfig::class],
        globalValues: ['scoped-resolver/test.intValue' => 42],
        scopeOverrides: ['scoped-resolver/test.intValue' => ['locale:fr' => 300]],
        scopedFields: [ScopedResolverIntConfig::class . '::intValue' => ['locale']],
        outContext: $context,
    );

    $context->in('locale', 'en'); // context is 'en', override is only for 'fr'

    $result = $resolver->resolved(ScopedResolverIntConfig::class, 'intValue');

    expect($result)->toBe(42); // falls back to global
});

it(
    'returns the matching override value from ScopedConfigResolver resolved when an override matches the current ScopeContext',
    function (): void {
        $context = null;
        $resolver = makeScopedResolver(
            axesMap: ['locale' => ['en', 'fr']],
            configClasses: [ScopedResolverIntConfig::class],
            globalValues: ['scoped-resolver/test.intValue' => 42],
            scopeOverrides: ['scoped-resolver/test.intValue' => ['locale:en' => 200]],
            scopedFields: [ScopedResolverIntConfig::class . '::intValue' => ['locale']],
            outContext: $context,
        );
    
        $context->in('locale', 'en');
    
        $result = $resolver->resolved(ScopedResolverIntConfig::class, 'intValue');
    
        expect($result)->toBe(200);
    }
);

it('decrypts secret override values via SecretCipher before casting', function (): void {
    // Use an identity cipher that returns the plaintext as-is for decryption
    $identityCipher = new class () implements SecretCipherInterface
    {
        public function encrypt(string $plaintext): string
        {
            return $plaintext;
        }

        public function decrypt(string $ciphertext): string
        {
            return $ciphertext;
        }
    };

    $context = null;
    // The override value is a JSON-encoded string (as would come from real encryption)
    $encryptedValue = json_encode('secret-locale-value');
    $resolver = makeScopedResolver(
        axesMap: ['locale' => ['en', 'fr']],
        configClasses: [ScopedResolverSecretConfig::class],
        scopeOverrides: ['scoped-resolver/test.secretValue' => ['locale:en' => $encryptedValue]],
        scopedFields: [ScopedResolverSecretConfig::class . '::secretValue' => ['locale']],
        outContext: $context,
        secretCipher: $identityCipher,
    );

    $context->in('locale', 'en');

    $result = $resolver->resolved(ScopedResolverSecretConfig::class, 'secretValue');

    expect($result)->toBe('secret-locale-value');
});

it('casts the override value to the declared type via ValueCaster', function (): void {
    $context = null;
    $resolver = makeScopedResolver(
        axesMap: ['locale' => ['en', 'fr']],
        configClasses: [ScopedResolverStringConfig::class],
        scopeOverrides: ['scoped-resolver/test.stringValue' => ['locale:en' => 'locale-string']],
        scopedFields: [ScopedResolverStringConfig::class . '::stringValue' => ['locale']],
        outContext: $context,
    );

    $context->in('locale', 'en');

    $result = $resolver->resolved(ScopedResolverStringConfig::class, 'stringValue');

    expect($result)->toBe('locale-string')
        ->and($result)->toBeString();
});

it('does not mutate the injected ScopeContext when resolvedAt is called', function (): void {
    $scopeRegistry = makeScopedResolverRegistry(['locale' => ['en', 'fr']]);
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([ScopedResolverStringConfig::class]);
    $storage = new InMemoryConfigStorage();
    $enumerator = new SignatureCandidateEnumerator($scopeRegistry);
    $overrideMatcher = new OverrideMatcher($enumerator);
    $injectedContext = new ScopeContext($scopeRegistry);
    $injectedContext->in('locale', 'en');
    $scopedFieldRegistry = new ScopedFieldRegistry($scopeRegistry);
    $scopedFieldRegistry->register(ScopedResolverStringConfig::class, 'stringValue', ['locale']);
    $scopedStorage = new InMemoryScopedConfigStorage();

    $resolver = new ScopedConfigResolver(
        configRegistry: $registry,
        configStorage: $storage,
        valueCaster: new ValueCaster(),
        secretCipher: new NullSecretCipher(),
        proxyLocator: new ProxyLocator(),
        preferenceRegistry: new PreferenceRegistry(),
        scopedConfigStorage: $scopedStorage,
        overrideMatcher: $overrideMatcher,
        scopeContext: $injectedContext,
        scopedFieldRegistry: $scopedFieldRegistry,
    );

    $stateBefore = $injectedContext->state();

    $explicitContext = new ScopeContext($scopeRegistry);
    $explicitContext->in('locale', 'fr');

    $resolver->resolvedAt(ScopedResolverStringConfig::class, 'stringValue', $explicitContext);

    expect($injectedContext->state())->toBe($stateBefore);
});

it(
    'resolves under an explicit ScopeContext passed to resolvedAt without reading the injected live context',
    function (): void {
        $liveContext = null;
        $scopeRegistry = makeScopedResolverRegistry(['locale' => ['en', 'fr']]);
        $builder = new ConfigRegistryBuilder();
        $registry = $builder->build([ScopedResolverStringConfig::class]);
        $storage = new InMemoryConfigStorage();
        $valueCaster = new ValueCaster();
        $secretCipher = new NullSecretCipher();
        $proxyLocator = new ProxyLocator();
        $preferenceRegistry = new PreferenceRegistry();
        $enumerator = new SignatureCandidateEnumerator($scopeRegistry);
        $overrideMatcher = new OverrideMatcher($enumerator);
        $injectedContext = new ScopeContext($scopeRegistry);
        $injectedContext->in('locale', 'en'); // injected live context is 'en'
    $scopedFieldRegistry = new ScopedFieldRegistry($scopeRegistry);
        $scopedFieldRegistry->register(ScopedResolverStringConfig::class, 'stringValue', ['locale']);
        $scopedStorage = new InMemoryScopedConfigStorage();
        $scopedStorage->saveOverride('scoped-resolver/test.stringValue', 'locale:fr', 'french-override');
    
        $resolver = new ScopedConfigResolver(
            configRegistry: $registry,
            configStorage: $storage,
            valueCaster: $valueCaster,
            secretCipher: $secretCipher,
            proxyLocator: $proxyLocator,
            preferenceRegistry: $preferenceRegistry,
            scopedConfigStorage: $scopedStorage,
            overrideMatcher: $overrideMatcher,
            scopeContext: $injectedContext,
            scopedFieldRegistry: $scopedFieldRegistry,
        );
    
        // Create an explicit context with 'fr' — the live context is 'en'
    $explicitContext = new ScopeContext($scopeRegistry);
        $explicitContext->in('locale', 'fr');
    
        $result = $resolver->resolvedAt(ScopedResolverStringConfig::class, 'stringValue', $explicitContext);
    
        expect($result)->toBe('french-override'); // used the explicit context, not injected live context
}
);
