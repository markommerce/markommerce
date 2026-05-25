<?php

declare(strict_types=1);

use Markommerce\Config\Proxy\ProxyLocator;

it('maps an original class FQN to a deterministic generated proxy FQN', function (): void {
    $locator = new ProxyLocator();

    $result = $locator->proxyClassFor('Acme\\Shop\\Config\\StoreConfig');

    expect($result)->toBe('Markommerce\\Config\\Generated\\Acme\\Shop\\Config\\StoreConfig_Resolved');
});

it('locates the same proxy FQN regardless of leading backslash variations in the original FQN', function (): void {
    $locator = new ProxyLocator();

    $withLeadingBackslash = $locator->proxyClassFor('\\Acme\\Shop\\Config\\StoreConfig');
    $withoutLeadingBackslash = $locator->proxyClassFor('Acme\\Shop\\Config\\StoreConfig');

    expect($withLeadingBackslash)->toBe($withoutLeadingBackslash);
});
