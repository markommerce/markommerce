<?php

declare(strict_types=1);

namespace Markommerce\Config\Cache;

use Markommerce\Config\ConfigResolver;
use Markommerce\Config\Contracts\ConfigCacheInterface;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Exceptions\InvalidConfigValueException;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\Scope\Context\ScopeContext;

class CachingConfigResolver
{
    public function __construct(
        private ConfigResolver $configResolver,
        private ConfigCacheInterface $configCache,
        private ConfigRegistry $configRegistry,
        private ScopeContext $scopeContext,
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
        $definition = $this->configRegistry->definition($configClass, $field);
        $cacheKey = $this->buildCacheKey($definition->key, $definition->axes, $this->scopeContext);

        return $this->configCache->get(
            $cacheKey,
            fn (): mixed => $this->configResolver->resolved($configClass, $field),
        );
    }

    /**
     * @param class-string $configClass
     *
     * @throws ConfigNotFoundException|InvalidConfigValueException
     */
    public function resolvedAt(
        string $configClass,
        string $field,
        ScopeContext $context,
    ): mixed {
        $definition = $this->configRegistry->definition($configClass, $field);
        $cacheKey = $this->buildCacheKey($definition->key, $definition->axes, $context);

        return $this->configCache->get(
            $cacheKey,
            fn (): mixed => $this->configResolver->resolvedAt($configClass, $field, $context),
        );
    }

    /**
     * @param list<string> $axes
     */
    private function buildCacheKey(
        string $configKey,
        array $axes,
        ScopeContext $context,
    ): string {
        if (empty($axes)) {
            return $configKey;
        }

        $parts = [];

        foreach ($axes as $axis) {
            $value = $context->get($axis);

            if ($value !== null) {
                $parts[] = "$axis:$value";
            }
        }

        if (empty($parts)) {
            return $configKey;
        }

        sort($parts);

        return $configKey . '|' . implode('|', $parts);
    }
}
