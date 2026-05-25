<?php

declare(strict_types=1);

namespace Markommerce\Config;

use Marko\Core\Container\PreferenceRegistry;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\Contracts\SecretCipherInterface;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Exceptions\InvalidConfigClassException;
use Markommerce\Config\Exceptions\InvalidConfigValueException;
use Markommerce\Config\Exceptions\ProxyNotGeneratedException;
use Markommerce\Config\Exceptions\SecretCipherException;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Config\Resolution\OverrideMatcher;
use Markommerce\Scope\Context\ScopeContext;

class ConfigResolver
{
    public function __construct(
        private ConfigRegistry $configRegistry,
        private ConfigStorageInterface $configStorage,
        private OverrideMatcher $overrideMatcher,
        private ValueCaster $valueCaster,
        private ScopeContext $scopeContext,
        private SecretCipherInterface $secretCipher,
        private ProxyLocator $proxyLocator,
        private PreferenceRegistry $preferenceRegistry,
    ) {}

    /**
     * @template T of object
     * @param class-string<T> $configClass
     * @return T
     *
     * @throws ProxyNotGeneratedException|InvalidConfigClassException
     */
    public function get(string $configClass): object
    {
        $effective = $this->preferenceRegistry->getPreference($configClass) ?? $configClass;

        if ($effective !== $configClass && !is_subclass_of($effective, $configClass)) {
            throw InvalidConfigClassException::nonSubclassPreference($configClass, $effective);
        }

        $proxyClass = $this->proxyLocator->proxyClassFor($effective);

        if (!class_exists($proxyClass)) {
            throw ProxyNotGeneratedException::forClass($effective);
        }

        /** @var T */
        return new $proxyClass($this);
    }

    /**
     * @param class-string $configClass
     *
     * @throws ConfigNotFoundException|InvalidConfigValueException
     */
    public function resolved(
        string $configClass,
        string $field,
    ): mixed {
        return $this->resolvedAt($configClass, $field, $this->scopeContext);
    }

    /**
     * @param class-string $configClass
     *
     * @throws ConfigNotFoundException|InvalidConfigValueException|SecretCipherException
     */
    public function resolvedAt(
        string $configClass,
        string $field,
        ScopeContext $explicitContext,
    ): mixed {
        $definition = $this->configRegistry->definition($configClass, $field);

        $row = $this->configStorage->load($definition->key);

        if ($row === null) {
            return $definition->defaultValue;
        }

        $overrideValue = $this->overrideMatcher->match($row, $definition->axes, $explicitContext);

        if ($overrideValue !== null) {
            if ($definition->secret) {
                $decrypted = $this->secretCipher->decrypt($overrideValue);
                $overrideValue = json_decode($decrypted, true);
            }

            return $this->valueCaster->cast($overrideValue, $definition);
        }

        if ($row->value !== null) {
            $rawValue = $row->value;

            if ($definition->secret) {
                $decrypted = $this->secretCipher->decrypt($rawValue);
                $rawValue = json_decode($decrypted, true);
            }

            return $this->valueCaster->cast($rawValue, $definition);
        }

        return $definition->defaultValue;
    }
}
