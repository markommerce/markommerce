<?php

declare(strict_types=1);

namespace Markommerce\Config\ValueObjects;

readonly class ConfigDefinition
{
    /**
     * @param class-string $configClass
     *
     * @phpstan-param class-string $configClass
     */
    public function __construct(
        public private(set) string $key,
        /** @phpstan-var class-string */
        public private(set) string $configClass,
        public private(set) string $field,
        public private(set) string $type,
        public private(set) mixed $defaultValue,
        public private(set) bool $secret,
    ) {}
}
