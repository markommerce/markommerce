<?php

declare(strict_types=1);

namespace Markommerce\Tax;

use Markommerce\Config\Contracts\ConfigResolverInterface;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Exceptions\InvalidConfigValueException;
use Markommerce\Config\Exceptions\SecretCipherException;
use Markommerce\Tax\Config\TaxConfig;

class TaxModeResolver
{
    public function __construct(
        private ConfigResolverInterface $configResolver,
    ) {}

    /**
     * @throws ConfigNotFoundException|InvalidConfigValueException|SecretCipherException
     */
    public function mode(): TaxMode
    {
        /** @var bool $pricesIncludeTax */
        $pricesIncludeTax = $this->configResolver->resolved(TaxConfig::class, 'pricesIncludeTax');

        return $pricesIncludeTax ? TaxMode::Inclusive : TaxMode::Exclusive;
    }
}
