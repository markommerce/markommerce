<?php

declare(strict_types=1);

namespace Markommerce\LayoutDemo\Config;

use Marko\Config\ConfigRepositoryInterface;

readonly class LayoutDemoConfig
{
    public function __construct(
        private ConfigRepositoryInterface $configRepository,
    ) {}

    public function isEnabled(): bool
    {
        if (!$this->configRepository->has('layout_demo.enabled')) {
            return false;
        }

        return $this->configRepository->getBool('layout_demo.enabled');
    }
}
