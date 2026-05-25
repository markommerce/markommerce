<?php

declare(strict_types=1);

namespace Markommerce\Config\Proxy;

class ProxyLocator
{
    private const string GENERATED_NAMESPACE_PREFIX = 'Markommerce\\Config\\Generated\\';

    private const string RESOLVED_SUFFIX = '_Resolved';

    /**
     * Returns the deterministic generated proxy FQN for the given original class FQN.
     *
     * @param class-string $originalClass
     * @return class-string
     */
    public function proxyClassFor(string $originalClass): string
    {
        $normalized = ltrim($originalClass, '\\');

        /** @var class-string */
        return self::GENERATED_NAMESPACE_PREFIX . $this->appendResolvedSuffix($normalized);
    }

    /**
     * Returns the proxy class name for the given config class.
     * Legacy method kept for backward compatibility.
     *
     * @param class-string $configClass
     * @return class-string
     */
    public function locate(string $configClass): string
    {
        return $this->proxyClassFor($configClass);
    }

    private function appendResolvedSuffix(string $normalizedFqn): string
    {
        $lastBackslash = strrpos($normalizedFqn, '\\');

        if ($lastBackslash === false) {
            return $normalizedFqn . self::RESOLVED_SUFFIX;
        }

        $namespace = substr($normalizedFqn, 0, $lastBackslash);
        $className = substr($normalizedFqn, $lastBackslash + 1);

        return $namespace . '\\' . $className . self::RESOLVED_SUFFIX;
    }
}
