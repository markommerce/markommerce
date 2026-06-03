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
use Markommerce\Tax\Config\TaxConfig;
use Markommerce\Tax\TaxMode;
use Markommerce\Tax\TaxModeResolver;

function makeTaxConfigResolver(bool $pricesIncludeTax = false): ConfigResolver
{
    $builder = new ConfigRegistryBuilder();
    $registry = $builder->build([TaxConfig::class]);

    $storage = new InMemoryConfigStorage();

    if ($pricesIncludeTax) {
        $storage->compareAndSave('tax/prices_include_tax', new ConfigRow(
            key: 'tax/prices_include_tax',
            value: true,
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

it('resolves the exclusive mode when prices do not include tax', function (): void {
    $configResolver = makeTaxConfigResolver(pricesIncludeTax: false);
    $resolver = new TaxModeResolver($configResolver);

    expect($resolver->mode())->toBe(TaxMode::Exclusive);
});

it('resolves the inclusive mode when prices include tax', function (): void {
    $configResolver = makeTaxConfigResolver(pricesIncludeTax: true);
    $resolver = new TaxModeResolver($configResolver);

    expect($resolver->mode())->toBe(TaxMode::Inclusive);
});

it('reads the tax mode through the injected config resolver', function (): void {
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

            return false;
        }
    };

    $resolver = new TaxModeResolver($fakeResolver);
    $resolver->mode();

    expect($fakeResolver->resolvedWasCalled)->toBeTrue()
        ->and($fakeResolver->lastClass)->toBe(TaxConfig::class)
        ->and($fakeResolver->lastField)->toBe('pricesIncludeTax');
});
