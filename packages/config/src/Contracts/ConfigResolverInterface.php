<?php

declare(strict_types=1);

namespace Markommerce\Config\Contracts;

use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Exceptions\InvalidConfigValueException;
use Markommerce\Config\Exceptions\SecretCipherException;

/**
 * Contract for resolving a single config value.
 *
 * Implemented by the base ConfigResolver and every decorator that replaces it
 * (caching, scoped). Consumers MUST depend on this interface rather than the
 * concrete ConfigResolver, because the container binds ConfigResolver::class to
 * a decorator (e.g. CachingConfigResolver) that is composed, not subclassed.
 */
interface ConfigResolverInterface
{
    /**
     * @param class-string $configClass
     *
     * @throws ConfigNotFoundException|InvalidConfigValueException|SecretCipherException
     */
    public function resolved(string $configClass, string $field): mixed;
}
