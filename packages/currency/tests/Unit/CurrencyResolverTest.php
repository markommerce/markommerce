<?php

declare(strict_types=1);

use Marko\Core\Container\PreferenceRegistry;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Registry\ConfigRegistryBuilder;
use Markommerce\Config\Storage\InMemoryConfigStorage;
use Markommerce\Config\ValueObjects\ConfigRow;
use Markommerce\Currency\Config\CurrencyConfig;
use Markommerce\Currency\CurrencyResolver;
use Markommerce\Money\Currency;
use Markommerce\Money\DefaultCurrencyRegistry;

function makeCurrencyConfigResolver(string $baseCode = 'USD'): ConfigResolver
{
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([CurrencyConfig::class]);

    $storage = new InMemoryConfigStorage();

    if ($baseCode !== 'USD') {
        $storage->compareAndSave('currency/base', new ConfigRow(
            key: 'currency/base',
            value: $baseCode,
            version: 0,
        ), 0);
    }

    return new ConfigResolver(
        configRegistry: $registry,
        configStorage: $storage,
        valueCaster: new ValueCaster(),
        secretCipher: new NullSecretCipher(),
        proxyLocator: new ProxyLocator(),
        preferenceRegistry: new PreferenceRegistry(),
    );
}

it('resolves the configured base code into a currency value object', function (): void {
    $configResolver = makeCurrencyConfigResolver('USD');
    $currencyRegistry = new DefaultCurrencyRegistry();
    $resolver = new CurrencyResolver($configResolver, $currencyRegistry);

    $currency = $resolver->base();

    expect($currency)->toBeInstanceOf(Currency::class)
        ->and($currency->code)->toBe('USD');
});

it('returns the overridden base currency when config provides a different code', function (): void {
    $configResolver = makeCurrencyConfigResolver('EUR');
    $currencyRegistry = new DefaultCurrencyRegistry();
    $resolver = new CurrencyResolver($configResolver, $currencyRegistry);

    $currency = $resolver->base();

    expect($currency)->toBeInstanceOf(Currency::class)
        ->and($currency->code)->toBe('EUR');
});

it('throws UnknownCurrencyException when the configured code is unknown', function (): void {
    $configResolver = makeCurrencyConfigResolver('XYZ');
    $currencyRegistry = new DefaultCurrencyRegistry();
    $resolver = new CurrencyResolver($configResolver, $currencyRegistry);

    expect(fn () => $resolver->base())
        ->toThrow(\Markommerce\Money\Exceptions\UnknownCurrencyException::class);
});

it('reads the base code through the injected config resolver', function (): void {
    $fakeResolver = new class () extends ConfigResolver {
        public bool $resolvedWasCalled = false;
        public string $lastClass = '';
        public string $lastField = '';

        public function __construct()
        {
            // Skip parent constructor — we override resolved()
        }

        public function resolved(
            string $configClass,
            string $field,
        ): mixed
        {
            $this->resolvedWasCalled = true;
            $this->lastClass = $configClass;
            $this->lastField = $field;

            return 'USD';
        }
    };

    $currencyRegistry = new DefaultCurrencyRegistry();
    $resolver = new CurrencyResolver($fakeResolver, $currencyRegistry);
    $resolver->base();

    expect($fakeResolver->resolvedWasCalled)->toBeTrue()
        ->and($fakeResolver->lastClass)->toBe(CurrencyConfig::class)
        ->and($fakeResolver->lastField)->toBe('base');
});
