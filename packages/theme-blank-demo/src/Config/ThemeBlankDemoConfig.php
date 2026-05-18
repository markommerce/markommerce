<?php

declare(strict_types=1);

namespace Markommerce\ThemeBlankDemo\Config;

use Marko\Config\ConfigRepositoryInterface;

readonly class ThemeBlankDemoConfig
{
    public function __construct(
        private ConfigRepositoryInterface $configRepository,
    ) {}

    public function isEnabled(): bool
    {
        if (!$this->configRepository->has('theme_blank_demo.enabled')) {
            return false;
        }

        return $this->configRepository->getBool('theme_blank_demo.enabled');
    }
}
