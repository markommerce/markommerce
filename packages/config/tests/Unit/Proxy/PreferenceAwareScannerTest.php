<?php

declare(strict_types=1);

use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Exceptions\PreferenceConflictException;
use Markommerce\Config\Exceptions\InvalidConfigClassException;
use Markommerce\Config\Proxy\PreferenceAwareScanner;
use Markommerce\Config\Tests\Fixtures\Proxy\BaseConfig;
use Markommerce\Config\Tests\Fixtures\Proxy\DeeplyExtendedConfig;
use Markommerce\Config\Tests\Fixtures\Proxy\ExtendedConfig;
use Markommerce\Config\Tests\Fixtures\Proxy\UnrelatedConfig;

it(
    'expands a list of registered classes by adding preference subclasses returned by PreferenceRegistry::getPreference',
    function (): void {
        $registry = new PreferenceRegistry();
        $registry->register(BaseConfig::class, ExtendedConfig::class);

        $scanner = new PreferenceAwareScanner($registry);

        $result = $scanner->expand([BaseConfig::class]);

        expect($result)->toContain(BaseConfig::class)
            ->and($result)->toContain(ExtendedConfig::class);
    },
);

it('follows nested preferences via PreferenceRegistry::getPreference (which already chains)', function (): void {
    $registry = new PreferenceRegistry();
    // Chain: BaseConfig -> ExtendedConfig -> DeeplyExtendedConfig
    $registry->register(BaseConfig::class, ExtendedConfig::class);
    $registry->register(ExtendedConfig::class, DeeplyExtendedConfig::class);

    $scanner = new PreferenceAwareScanner($registry);

    $result = $scanner->expand([BaseConfig::class]);

    // getPreference follows the chain and returns the final concrete class DeeplyExtendedConfig
    expect($result)->toContain(BaseConfig::class)
        ->and($result)->toContain(DeeplyExtendedConfig::class);
});

it(
    'throws InvalidConfigClassException when a preferred class is not a subclass of the original config class',
    function (): void {
        $registry = new PreferenceRegistry();
        // UnrelatedConfig is not a subclass of BaseConfig
        $registry->register(BaseConfig::class, UnrelatedConfig::class);

        $scanner = new PreferenceAwareScanner($registry);

        expect(fn () => $scanner->expand([BaseConfig::class]))
            ->toThrow(InvalidConfigClassException::class);
    },
);

it(
    'propagates PreferenceConflictException::circularPreference unchanged when PreferenceRegistry detects a cycle',
    function (): void {
        $registry = new PreferenceRegistry();
        // Create a cycle: ExtendedConfig -> DeeplyExtendedConfig -> ExtendedConfig
        $registry->register(ExtendedConfig::class, DeeplyExtendedConfig::class);
        $registry->register(DeeplyExtendedConfig::class, ExtendedConfig::class);

        $scanner = new PreferenceAwareScanner($registry);

        expect(fn () => $scanner->expand([ExtendedConfig::class]))
            ->toThrow(PreferenceConflictException::class);
    },
);
