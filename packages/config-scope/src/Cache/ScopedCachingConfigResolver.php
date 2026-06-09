<?php

declare(strict_types=1);

namespace Markommerce\ConfigScope\Cache;

use Marko\Core\Attributes\Preference;
use Markommerce\Config\Cache\CachingConfigResolver;
use Markommerce\Config\Contracts\ConfigCacheInterface;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Registry\ConfigRegistry;
use Markommerce\ConfigScope\ScopedConfigResolver;
use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Metadata\ScopedFieldRegistry;

#[Preference(replaces: CachingConfigResolver::class)]
class ScopedCachingConfigResolver extends CachingConfigResolver
{
    public function __construct(
        ScopedConfigResolver $configResolver,
        ConfigCacheInterface $configCache,
        ConfigRegistry $configRegistry,
        private ScopedFieldRegistry $scopedFieldRegistry,
        private ScopeContext $scopeContext,
    ) {
        parent::__construct($configResolver, $configCache, $configRegistry);
    }

    /**
     * @param class-string $configClass
     *
     * @throws ConfigNotFoundException
     */
    protected function buildCacheKey(
        string $configClass,
        string $field,
    ): string
    {
        $baseKey = parent::buildCacheKey($configClass, $field);

        $definition = $this->configRegistry->definition($configClass, $field);
        $axes = $this->scopedFieldRegistry->axesForProperty($definition->configClass, $definition->field);

        if ($axes === []) {
            return $baseKey;
        }

        $axisParts = [];
        foreach ($axes as $axis) {
            $value = $this->scopeContext->get($axis);
            if ($value !== null) {
                $axisParts[] = $axis . ':' . $value;
            }
        }

        if ($axisParts === []) {
            return $baseKey;
        }

        return $baseKey . '|' . implode('|', $axisParts);
    }
}
