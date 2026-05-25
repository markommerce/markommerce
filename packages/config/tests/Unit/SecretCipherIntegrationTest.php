<?php

declare(strict_types=1);

use Marko\Core\Container\PreferenceRegistry;
use Markommerce\Config\Attributes\Config;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\ConfigWriter;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Encryption\SodiumSecretCipher;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Resolution\OverrideMatcher;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Config\Tests\Fakes\FakeScopeRegistry;
use Markommerce\Config\Tests\Fakes\IdentitySecretCipher;
use Markommerce\Scope\Attributes\Scoped;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;
use Markommerce\Scope\Signature\ScopeSignature;

// --- Fixture config classes ---

class SecretGlobalConfig
{
    #[Config(key: 'secret/test.apiKey', secret: true)]
    public string $apiKey = 'default-key';
}

class SecretScopedConfig
{
    #[Config(key: 'secret/scoped.token', secret: true)]
    #[Scoped(axes: ['store'])]
    public string $token = 'default-token';
}

class NonSecretConfig
{
    #[Config(key: 'nonsecret/test.value')]
    public string $value = 'default-value';
}

// --- Helpers ---

function buildSecretRegistry(array $configClasses = []): \Markommerce\Config\Registry\ConfigRegistry
{
    $builder = new ConfigRegistryBuilder();

    return $builder->build(
        $configClasses,
        new FakeScopeRegistry(['store']),
    );
}

function buildSecretWriter(InMemoryConfigStorage $storage, array $configClasses): ConfigWriter
{
    return new ConfigWriter(
        registry: buildSecretRegistry($configClasses),
        storage: $storage,
        cipher: new IdentitySecretCipher(),
    );
}

function buildSecretResolver(InMemoryConfigStorage $storage, array $configClasses): ConfigResolver
{
    $fakeScopeRegistry = new FakeScopeRegistry(['store']);
    $registry = buildSecretRegistry($configClasses);
    $enumerator = new SignatureCandidateEnumerator($fakeScopeRegistry);
    $overrideMatcher = new OverrideMatcher($enumerator);
    $context = new ScopeContext($fakeScopeRegistry);

    return new ConfigResolver(
        configRegistry: $registry,
        configStorage: $storage,
        overrideMatcher: $overrideMatcher,
        valueCaster: new ValueCaster(),
        scopeContext: $context,
        secretCipher: new IdentitySecretCipher(),
        proxyLocator: new ProxyLocator(),
        preferenceRegistry: new PreferenceRegistry(),
    );
}

// --- Tests ---

it('encrypts the value with SecretCipher when writing a #[Config(secret: true)] global', function (): void {
    $storage = new InMemoryConfigStorage();
    $writer = buildSecretWriter($storage, [SecretGlobalConfig::class]);

    $writer->setGlobal('secret/test.apiKey', 'my-secret-api-key');

    $row = $storage->load('secret/test.apiKey');
    expect($row)->not->toBeNull();

    // IdentitySecretCipher: encrypt(json_encode('my-secret-api-key')) == json_encode('my-secret-api-key')
    expect($row->value)->toBe(json_encode('my-secret-api-key'));
});

it('encrypts the value with SecretCipher when writing a #[Config(secret: true)] override', function (): void {
    $storage = new InMemoryConfigStorage();
    $writer = buildSecretWriter($storage, [SecretScopedConfig::class]);

    $signature = new ScopeSignature(['store' => '1']);
    $writer->setOverride('secret/scoped.token', $signature, 'store-secret-token');

    $row = $storage->load('secret/scoped.token');
    expect($row)->not->toBeNull();

    // IdentitySecretCipher: encrypt(json_encode('store-secret-token')) == json_encode('store-secret-token')
    expect($row->overrides[$signature->toString()])->toBe(json_encode('store-secret-token'));
});

it('decrypts the stored ciphertext when resolving a #[Config(secret: true)] global', function (): void {
    $storage = new InMemoryConfigStorage();

    // Pre-seed with the "ciphertext" (which for IdentitySecretCipher is just json_encode of the plaintext)
    $storage->compareAndSave('secret/test.apiKey', new \Markommerce\Config\ValueObjects\ConfigRow(
        key: 'secret/test.apiKey',
        value: json_encode('stored-api-key'),
        overrides: [],
        version: 0,
    ), 0);

    $resolver = buildSecretResolver($storage, [SecretGlobalConfig::class]);

    $result = $resolver->resolved(SecretGlobalConfig::class, 'apiKey');

    expect($result)->toBe('stored-api-key');
});

it('decrypts the stored ciphertext when resolving a #[Config(secret: true)] override', function (): void {
    $fakeScopeRegistry = new \Markommerce\Config\Tests\Fakes\FakeScopeRegistry(['store']);
    $registry = buildSecretRegistry([SecretScopedConfig::class]);
    $storage = new InMemoryConfigStorage();

    // Pre-seed with an override ciphertext
    $storage->compareAndSave('secret/scoped.token', new \Markommerce\Config\ValueObjects\ConfigRow(
        key: 'secret/scoped.token',
        value: null,
        overrides: ['store:eu' => json_encode('override-secret-token')],
        version: 0,
    ), 0);

    $scopeRegistry = new class (['store' => ['default', 'eu']]) implements \Markommerce\Scope\Registry\ScopeRegistryInterface {
        /** @var array<string, \Markommerce\Scope\Axis\ScopeAxis> */
        private array $builtAxes;

        /** @param array<string, list<string>> $axes */
        public function __construct(array $axes)
        {
            $this->builtAxes = [];

            foreach ($axes as $name => $paths) {
                $default = $paths[0] ?? 'default';
                $hierarchy = new \Markommerce\Scope\Hierarchy\ScopeHierarchy($paths);
                $this->builtAxes[$name] = new \Markommerce\Scope\Axis\ScopeAxis(name: $name, hierarchy: $hierarchy, default: $default);
            }
        }

        public function hasAxis(string $name): bool
        {
            return isset($this->builtAxes[$name]);
        }

        /** @throws \Markommerce\Scope\Exceptions\UnknownAxisException */
        public function getAxis(string $name): \Markommerce\Scope\Axis\ScopeAxis
        {
            return $this->builtAxes[$name] ?? throw \Markommerce\Scope\Exceptions\UnknownAxisException::forAxis($name);
        }

        /** @return list<string> */
        public function listAxes(): array
        {
            return array_keys($this->builtAxes);
        }

        /** @throws \Markommerce\Scope\Exceptions\UnknownAxisException */
        public function getHierarchy(string $axisName): \Markommerce\Scope\Hierarchy\ScopeHierarchy
        {
            return $this->getAxis($axisName)->hierarchy;
        }
    };

    $context = new ScopeContext($scopeRegistry);
    $context->in('store', 'eu');

    $enumerator = new SignatureCandidateEnumerator($scopeRegistry);
    $overrideMatcher = new OverrideMatcher($enumerator);

    $resolverRegistry = (new ConfigRegistryBuilder())->build([SecretScopedConfig::class], $scopeRegistry);

    $resolver = new ConfigResolver(
        configRegistry: $resolverRegistry,
        configStorage: $storage,
        overrideMatcher: $overrideMatcher,
        valueCaster: new ValueCaster(),
        scopeContext: $context,
        secretCipher: new IdentitySecretCipher(),
        proxyLocator: new ProxyLocator(),
        preferenceRegistry: new PreferenceRegistry(),
    );

    $result = $resolver->resolved(SecretScopedConfig::class, 'token');

    expect($result)->toBe('override-secret-token');
});

it('does not invoke the cipher when the property is not marked secret', function (): void {
    $storage = new InMemoryConfigStorage();

    // NullSecretCipher throws when called — proves cipher is bypassed
    $registry = (new ConfigRegistryBuilder())->build([NonSecretConfig::class], new FakeScopeRegistry());
    $writer = new ConfigWriter(
        registry: $registry,
        storage: $storage,
        cipher: new NullSecretCipher(),
    );

    $writer->setGlobal('nonsecret/test.value', 'plain-value');

    $row = $storage->load('nonsecret/test.value');
    expect($row)->not->toBeNull()
        ->and($row->value)->toBe('plain-value');

    // Also verify resolver doesn't call cipher
    $resolver = new ConfigResolver(
        configRegistry: $registry,
        configStorage: $storage,
        overrideMatcher: new OverrideMatcher(new SignatureCandidateEnumerator(new FakeScopeRegistry())),
        valueCaster: new ValueCaster(),
        scopeContext: new ScopeContext(new FakeScopeRegistry()),
        secretCipher: new NullSecretCipher(),
        proxyLocator: new ProxyLocator(),
        preferenceRegistry: new PreferenceRegistry(),
    );

    $result = $resolver->resolved(NonSecretConfig::class, 'value');
    expect($result)->toBe('plain-value');
});

it('round-trips a secret value through write then read using SodiumSecretCipher with a real 32-byte key', function (): void {
    $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    $cipher = new SodiumSecretCipher($key);

    $storage = new InMemoryConfigStorage();
    $registry = (new ConfigRegistryBuilder())->build([SecretGlobalConfig::class], new FakeScopeRegistry(['store']));

    $writer = new ConfigWriter(
        registry: $registry,
        storage: $storage,
        cipher: $cipher,
    );

    $originalValue = 'super-secret-api-key-12345';
    $writer->setGlobal('secret/test.apiKey', $originalValue);

    $resolver = new ConfigResolver(
        configRegistry: $registry,
        configStorage: $storage,
        overrideMatcher: new OverrideMatcher(new SignatureCandidateEnumerator(new FakeScopeRegistry(['store']))),
        valueCaster: new ValueCaster(),
        scopeContext: new ScopeContext(new FakeScopeRegistry(['store'])),
        secretCipher: $cipher,
        proxyLocator: new ProxyLocator(),
        preferenceRegistry: new PreferenceRegistry(),
    );

    $resolved = $resolver->resolved(SecretGlobalConfig::class, 'apiKey');

    expect($resolved)->toBe($originalValue);
});
