<?php

declare(strict_types=1);

namespace Markommerce\Config\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Config
{
    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(
        public string $key,
        public bool $secret = false,
    ) {
        if ($key === '') {
            throw new \InvalidArgumentException('Config key must not be empty.');
        }
    }
}
