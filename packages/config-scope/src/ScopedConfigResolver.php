<?php

declare(strict_types=1);

namespace Markommerce\ConfigScope;

use Marko\Core\Attributes\Preference;
use Marko\Core\Container\PreferenceRegistry;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\Contracts\SecretCipherInterface;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Exceptions\InvalidConfigValueException;
use Markommerce\Config\Exceptions\SecretCipherException;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\ConfigScope\Contracts\ScopedConfigStorageInterface;
use Markommerce\ConfigScope\Resolution\OverrideMatcher;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;

#[Preference(replaces: ConfigResolver::class)]
class ScopedConfigResolver extends ConfigResolver
{
    public function __construct(
        ConfigRegistry $configRegistry,
        ConfigStorageInterface $configStorage,
        ValueCaster $valueCaster,
        SecretCipherInterface $secretCipher,
        ProxyLocator $proxyLocator,
        PreferenceRegistry $preferenceRegistry,
        private ScopedConfigStorageInterface $scopedConfigStorage,
        private OverrideMatcher $overrideMatcher,
        private ScopeContext $scopeContext,
        private ScopedFieldRegistry $scopedFieldRegistry,
    ) {
        parent::__construct(
            $configRegistry,
            $configStorage,
            $valueCaster,
            $secretCipher,
            $proxyLocator,
            $preferenceRegistry,
        );
    }

    /**
     * @param class-string $configClass
     *
     * @throws ConfigNotFoundException|InvalidConfigValueException|SecretCipherException
     */
    public function resolved(string $configClass, string $field): mixed
    {
        return $this->resolvedAt($configClass, $field, $this->scopeContext);
    }

    /**
     * @param class-string $configClass
     *
     * @throws ConfigNotFoundException|InvalidConfigValueException|SecretCipherException
     */
    public function resolvedAt(string $configClass, string $field, ScopeContext $context): mixed
    {
        $definition = $this->configRegistry->definition($configClass, $field);
        $axes = $this->scopedFieldRegistry->axesForProperty($definition->configClass, $definition->field);

        if ($axes === []) {
            return parent::resolved($configClass, $field);
        }

        $overrides = $this->scopedConfigStorage->loadOverrides($definition->key);
        $overrideValue = $this->overrideMatcher->match($overrides, $axes, $context);

        if ($overrideValue !== null) {
            if ($definition->secret) {
                $decrypted = $this->secretCipher->decrypt($overrideValue);
                $overrideValue = json_decode($decrypted, true);
            }

            return $this->valueCaster->cast($overrideValue, $definition);
        }

        return parent::resolved($configClass, $field);
    }
}
