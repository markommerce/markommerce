<?php

declare(strict_types=1);

namespace Markommerce\FrontendDemo\Config;

use Marko\Config\ConfigRepositoryInterface;

readonly class FrontendDemoConfig
{
    public function __construct(
        private ConfigRepositoryInterface $configRepository,
    ) {}

    public function isEnabled(): bool
    {
        if (!$this->configRepository->has('frontend_demo.enabled')) {
            return false;
        }

        return $this->configRepository->getBool('frontend_demo.enabled');
    }
}
