<?php

declare(strict_types=1);

namespace Markommerce\Config\Cache;

use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Contracts\ConfigCacheInterface;
use Markommerce\Config\Contracts\ConfigResolverInterface;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Exceptions\InvalidConfigValueException;
use Markommerce\Config\Registry\ConfigRegistry;

class CachingConfigResolver implements ConfigResolverInterface
{
    public function __construct(
        protected ConfigResolver $configResolver,
        protected ConfigCacheInterface $configCache,
        protected ConfigRegistry $configRegistry,
    ) {}

    /**
     * @param class-string $configClass
     *
     * @throws ConfigNotFoundException|InvalidConfigValueException
     */
    public function resolved(
        string $configClass,
        string $field,
    ): mixed {
        $cacheKey = $this->buildCacheKey($configClass, $field);

        return $this->configCache->get(
            $cacheKey,
            fn (): mixed => $this->configResolver->resolved($configClass, $field),
        );
    }

    /**
     * @param class-string $configClass
     *
     * @throws ConfigNotFoundException
     */
    protected function buildCacheKey(string $configClass, string $field): string
    {
        $definition = $this->configRegistry->definition($configClass, $field);

        return $definition->key;
    }
}
