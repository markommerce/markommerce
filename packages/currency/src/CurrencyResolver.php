<?php

declare(strict_types=1);

namespace Markommerce\Currency;

use Markommerce\Config\Contracts\ConfigResolverInterface;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Exceptions\InvalidConfigValueException;
use Markommerce\Config\Exceptions\SecretCipherException;
use Markommerce\Currency\Config\CurrencyConfig;
use Markommerce\Money\Contracts\CurrencyRegistryInterface;
use Markommerce\Money\Currency;
use Markommerce\Money\Exceptions\UnknownCurrencyException;

class CurrencyResolver
{
    public function __construct(
        private ConfigResolverInterface $configResolver,
        private CurrencyRegistryInterface $currencyRegistry,
    ) {}

    /**
     * @throws ConfigNotFoundException|InvalidConfigValueException|SecretCipherException|UnknownCurrencyException
     */
    public function base(): Currency
    {
        /** @var string $code */
        $code = $this->configResolver->resolved(CurrencyConfig::class, 'base');

        return $this->currencyRegistry->get($code);
    }
}
