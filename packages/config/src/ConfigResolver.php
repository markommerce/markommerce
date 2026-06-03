<?php

declare(strict_types=1);

namespace Markommerce\Config;

use Marko\Core\Container\PreferenceRegistry;
use Markommerce\Config\Casting\ValueCaster;
use Markommerce\Config\Contracts\ConfigResolverInterface;
use Markommerce\Config\Contracts\ConfigStorageInterface;
use Markommerce\Config\Contracts\SecretCipherInterface;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Exceptions\InvalidConfigClassException;
use Markommerce\Config\Exceptions\InvalidConfigValueException;
use Markommerce\Config\Exceptions\ProxyNotGeneratedException;
use Markommerce\Config\Exceptions\SecretCipherException;
use Markommerce\Config\Proxy\ProxyLocator;
use Markommerce\Config\Registry\ConfigRegistry;

class ConfigResolver implements ConfigResolverInterface
{
    public function __construct(
        protected ConfigRegistry $configRegistry,
        protected ConfigStorageInterface $configStorage,
        protected ValueCaster $valueCaster,
        protected SecretCipherInterface $secretCipher,
        protected ProxyLocator $proxyLocator,
        protected PreferenceRegistry $preferenceRegistry,
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
     * @throws ConfigNotFoundException|InvalidConfigValueException|SecretCipherException
     */
    public function resolved(
        string $configClass,
        string $field,
    ): mixed {
        $definition = $this->configRegistry->definition($configClass, $field);

        $row = $this->configStorage->load($definition->key);

        if ($row === null) {
            return $definition->defaultValue;
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
