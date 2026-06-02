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
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Config\Tests\Fakes\IdentitySecretCipher;
use Markommerce\Config\ValueObjects\ConfigRow;

// --- Fixture config classes ---

class SecretGlobalConfig
{
    #[Config(key: 'secret/test.apiKey', secret: true)]
    public string $apiKey = 'default-key';
}

class NonSecretConfig
{
    #[Config(key: 'nonsecret/test.value')]
    public string $value = 'default-value';
}

// --- Helpers ---

function buildSecretRegistry(array $configClasses = []): ConfigRegistry
{
    $builder = new ConfigRegistryBuilder();

    return $builder->build($configClasses);
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
    $registry = buildSecretRegistry($configClasses);

    return new ConfigResolver(
        configRegistry: $registry,
        configStorage: $storage,
        valueCaster: new ValueCaster(),
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

it('decrypts the stored ciphertext when resolving a #[Config(secret: true)] global', function (): void {
    $storage = new InMemoryConfigStorage();

    // Pre-seed with the "ciphertext" (which for IdentitySecretCipher is just json_encode of the plaintext)
    $storage->compareAndSave('secret/test.apiKey', new ConfigRow(
        key: 'secret/test.apiKey',
        value: json_encode('stored-api-key'),
        version: 0,
    ), 0);

    $resolver = buildSecretResolver($storage, [SecretGlobalConfig::class]);

    $result = $resolver->resolved(SecretGlobalConfig::class, 'apiKey');

    expect($result)->toBe('stored-api-key');
});

it('does not invoke the cipher when the property is not marked secret', function (): void {
    $storage = new InMemoryConfigStorage();

    // NullSecretCipher throws when called — proves cipher is bypassed
    $registry = (new ConfigRegistryBuilder())->build([NonSecretConfig::class]);
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
        valueCaster: new ValueCaster(),
        secretCipher: new NullSecretCipher(),
        proxyLocator: new ProxyLocator(),
        preferenceRegistry: new PreferenceRegistry(),
    );

    $result = $resolver->resolved(NonSecretConfig::class, 'value');
    expect($result)->toBe('plain-value');
});

it(
    'round-trips a secret value through write then read using SodiumSecretCipher with a real 32-byte key',
    function (): void {
        $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $cipher = new SodiumSecretCipher($key);

        $storage = new InMemoryConfigStorage();
        $registry = (new ConfigRegistryBuilder())->build([SecretGlobalConfig::class]);

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
            valueCaster: new ValueCaster(),
            secretCipher: $cipher,
            proxyLocator: new ProxyLocator(),
            preferenceRegistry: new PreferenceRegistry(),
        );

        $resolved = $resolver->resolved(SecretGlobalConfig::class, 'apiKey');

        expect($resolved)->toBe($originalValue);
    },
);
