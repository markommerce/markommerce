<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Markommerce\Config\Exceptions\AxisNotDeclaredException;
use Markommerce\Config\Exceptions\ConfigKeyConflictException;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Exceptions\InvalidConfigClassException;
use Markommerce\Config\Exceptions\InvalidConfigValueException;
use Markommerce\Config\Exceptions\ProxyNotGeneratedException;
use Markommerce\Config\Exceptions\SecretCipherException;
use Markommerce\Config\Exceptions\StaleConfigWriteException;

it(
    'creates ConfigKeyConflictException when two definitions claim the same key, naming both classes and the duplicate key',
    function (): void {
        $exception = ConfigKeyConflictException::forKey(
            'markommerce/catalog.grid_page_size',
            'Markommerce\Catalog\Config\CatalogConfig',
            'Markommerce\Catalog\Config\OverrideCatalogConfig',
        );
    
        expect($exception)->toBeInstanceOf(ConfigKeyConflictException::class)
            ->and($exception)->toBeInstanceOf(MarkoException::class)
            ->and($exception->getMessage())->toContain('markommerce/catalog.grid_page_size')
            ->and($exception->getMessage())->toContain('Markommerce\Catalog\Config\CatalogConfig')
            ->and($exception->getMessage())->toContain('Markommerce\Catalog\Config\OverrideCatalogConfig')
            ->and($exception->getContext())->not->toBeEmpty()
            ->and($exception->getSuggestion())->not->toBeEmpty();
    }
);

it(
    'creates InvalidConfigClassException via factory configClassHasRequiredConstructor when a config class has required constructor params',
    function (): void {
        $exception = InvalidConfigClassException::configClassHasRequiredConstructor(
            'Markommerce\Catalog\Config\CatalogConfig',
        );
    
        expect($exception)->toBeInstanceOf(InvalidConfigClassException::class)
            ->and($exception)->toBeInstanceOf(MarkoException::class)
            ->and($exception->getMessage())->toContain('Markommerce\Catalog\Config\CatalogConfig')
            ->and($exception->getContext())->not->toBeEmpty()
            ->and($exception->getSuggestion())->not->toBeEmpty();
    }
);

it(
    'creates InvalidConfigClassException via factory propertyMissingDefaultOrNullability when a non-nullable #[Config] property has no default',
    function (): void {
        $exception = InvalidConfigClassException::propertyMissingDefaultOrNullability(
            'Markommerce\Catalog\Config\CatalogConfig',
            'gridPageSize',
        );
    
        expect($exception)->toBeInstanceOf(InvalidConfigClassException::class)
            ->and($exception)->toBeInstanceOf(MarkoException::class)
            ->and($exception->getMessage())->toContain('Markommerce\Catalog\Config\CatalogConfig')
            ->and($exception->getMessage())->toContain('gridPageSize')
            ->and($exception->getContext())->not->toBeEmpty()
            ->and($exception->getSuggestion())->not->toBeEmpty();
    }
);

it(
    'creates InvalidConfigClassException via factory nonSubclassPreference when a preferred class is not a subclass of the original',
    function (): void {
        $exception = InvalidConfigClassException::nonSubclassPreference(
            'Markommerce\Catalog\Config\CatalogConfig',
            'Markommerce\Vendor\Config\VendorConfig',
        );
    
        expect($exception)->toBeInstanceOf(InvalidConfigClassException::class)
            ->and($exception)->toBeInstanceOf(MarkoException::class)
            ->and($exception->getMessage())->toContain('Markommerce\Catalog\Config\CatalogConfig')
            ->and($exception->getMessage())->toContain('Markommerce\Vendor\Config\VendorConfig')
            ->and($exception->getContext())->not->toBeEmpty()
            ->and($exception->getSuggestion())->not->toBeEmpty();
    }
);

it(
    'creates InvalidConfigClassException for properties without #[Config] or with unsupported types (union/intersection/readonly)',
    function (): void {
        $withoutAttribute = InvalidConfigClassException::propertyWithoutConfigAttribute(
            'Markommerce\Catalog\Config\CatalogConfig',
            'gridPageSize',
        );
        $withUnsupportedType = InvalidConfigClassException::propertyWithUnsupportedType(
            'Markommerce\Catalog\Config\CatalogConfig',
            'status',
            'union types are not supported',
        );
    
        expect($withoutAttribute)->toBeInstanceOf(InvalidConfigClassException::class)
            ->and($withoutAttribute)->toBeInstanceOf(MarkoException::class)
            ->and($withoutAttribute->getMessage())->toContain('Markommerce\Catalog\Config\CatalogConfig')
            ->and($withoutAttribute->getMessage())->toContain('gridPageSize')
            ->and($withoutAttribute->getContext())->not->toBeEmpty()
            ->and($withoutAttribute->getSuggestion())->not->toBeEmpty()
            ->and($withUnsupportedType)->toBeInstanceOf(InvalidConfigClassException::class)
            ->and($withUnsupportedType->getMessage())->toContain('Markommerce\Catalog\Config\CatalogConfig')
            ->and($withUnsupportedType->getMessage())->toContain('status')
            ->and($withUnsupportedType->getMessage())->toContain('union types are not supported')
            ->and($withUnsupportedType->getContext())->not->toBeEmpty()
            ->and($withUnsupportedType->getSuggestion())->not->toBeEmpty();
    }
);

it(
    'creates InvalidConfigValueException carrying the offending key, raw stored value, and target PHP type',
    function (): void {
        $exception = InvalidConfigValueException::forKey(
            'markommerce/catalog.grid_page_size',
            'not-a-number',
            'int',
        );
    
        expect($exception)->toBeInstanceOf(InvalidConfigValueException::class)
            ->and($exception)->toBeInstanceOf(MarkoException::class)
            ->and($exception->getMessage())->toContain('markommerce/catalog.grid_page_size')
            ->and($exception->getMessage())->toContain('not-a-number')
            ->and($exception->getMessage())->toContain('int')
            ->and($exception->getContext())->not->toBeEmpty()
            ->and($exception->getSuggestion())->not->toBeEmpty();
    }
);

it(
    'creates ProxyNotGeneratedException with a config:generate suggestion when a typed proxy is missing at runtime',
    function (): void {
        $exception = ProxyNotGeneratedException::forClass('Markommerce\Catalog\Config\CatalogConfig');
    
        expect($exception)->toBeInstanceOf(ProxyNotGeneratedException::class)
            ->and($exception)->toBeInstanceOf(MarkoException::class)
            ->and($exception->getMessage())->toContain('Markommerce\Catalog\Config\CatalogConfig')
            ->and($exception->getContext())->not->toBeEmpty()
            ->and($exception->getSuggestion())->toContain('config:generate');
    }
);

it(
    'creates StaleConfigWriteException naming the key and retry count after optimistic-lock retries are exhausted',
    function (): void {
        $exception = StaleConfigWriteException::afterRetries('markommerce/catalog.grid_page_size', 3);
    
        expect($exception)->toBeInstanceOf(StaleConfigWriteException::class)
            ->and($exception)->toBeInstanceOf(MarkoException::class)
            ->and($exception->getMessage())->toContain('markommerce/catalog.grid_page_size')
            ->and($exception->getMessage())->toContain('3')
            ->and($exception->getContext())->not->toBeEmpty()
            ->and($exception->getSuggestion())->not->toBeEmpty();
    }
);

it(
    'creates AxisNotDeclaredException when a write signature references an axis not declared on the property',
    function (): void {
        $exception = AxisNotDeclaredException::forPropertyAndAxis(
            'markommerce/catalog.grid_page_size',
            'store',
        );
    
        expect($exception)->toBeInstanceOf(AxisNotDeclaredException::class)
            ->and($exception)->toBeInstanceOf(MarkoException::class)
            ->and($exception->getMessage())->toContain('markommerce/catalog.grid_page_size')
            ->and($exception->getMessage())->toContain('store')
            ->and($exception->getContext())->not->toBeEmpty()
            ->and($exception->getSuggestion())->not->toBeEmpty();
    }
);

it(
    'creates SecretCipherException via factory notConfigured naming MARKOMMERCE_CONFIG_SECRET_KEY in the suggestion',
    function (): void {
        $exception = SecretCipherException::notConfigured();
    
        expect($exception)->toBeInstanceOf(SecretCipherException::class)
            ->and($exception)->toBeInstanceOf(MarkoException::class)
            ->and($exception->getContext())->not->toBeEmpty()
            ->and($exception->getSuggestion())->toContain('MARKOMMERCE_CONFIG_SECRET_KEY');
    }
);

it(
    'creates SecretCipherException via factory sodiumUnavailable / invalidKeyLength / tamperedCiphertext',
    function (): void {
        $sodiumUnavailable = SecretCipherException::sodiumUnavailable();
        $invalidKeyLength = SecretCipherException::invalidKeyLength(16, 32);
        $tamperedCiphertext = SecretCipherException::tamperedCiphertext('markommerce/catalog.secret_key');
    
        expect($sodiumUnavailable)->toBeInstanceOf(SecretCipherException::class)
            ->and($sodiumUnavailable)->toBeInstanceOf(MarkoException::class)
            ->and($sodiumUnavailable->getMessage())->not->toBeEmpty()
            ->and($sodiumUnavailable->getContext())->not->toBeEmpty()
            ->and($sodiumUnavailable->getSuggestion())->not->toBeEmpty()
            ->and($invalidKeyLength)->toBeInstanceOf(SecretCipherException::class)
            ->and($invalidKeyLength->getMessage())->toContain('16')
            ->and($invalidKeyLength->getMessage())->toContain('32')
            ->and($invalidKeyLength->getContext())->not->toBeEmpty()
            ->and($invalidKeyLength->getSuggestion())->not->toBeEmpty()
            ->and($tamperedCiphertext)->toBeInstanceOf(SecretCipherException::class)
            ->and($tamperedCiphertext->getMessage())->toContain('markommerce/catalog.secret_key')
            ->and($tamperedCiphertext->getContext())->not->toBeEmpty()
            ->and($tamperedCiphertext->getSuggestion())->not->toBeEmpty();
    }
);

it(
    'creates ConfigNotFoundException with the missing key in message and a how-to-register suggestion',
    function (): void {
        $exception = ConfigNotFoundException::forKey('markommerce/catalog.grid_page_size');
    
        expect($exception)->toBeInstanceOf(ConfigNotFoundException::class)
            ->and($exception)->toBeInstanceOf(MarkoException::class)
            ->and($exception->getMessage())->toContain('markommerce/catalog.grid_page_size')
            ->and($exception->getContext())->not->toBeEmpty()
            ->and($exception->getSuggestion())->toContain('register');
    }
);
