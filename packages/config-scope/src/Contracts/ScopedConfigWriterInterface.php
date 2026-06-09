<?php

declare(strict_types=1);

namespace Markommerce\ConfigScope\Contracts;

use Markommerce\Config\Contracts\ConfigWriterInterface;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\ConfigScope\Exceptions\AxisNotDeclaredException;
use Markommerce\Scope\Signature\ScopeSignature;

interface ScopedConfigWriterInterface extends ConfigWriterInterface
{
    /**
     * @throws ConfigNotFoundException|AxisNotDeclaredException
     */
    public function setOverride(
        string $key,
        ScopeSignature $signature,
        mixed $value,
    ): void;

    /**
     * @throws ConfigNotFoundException|AxisNotDeclaredException
     */
    public function unsetOverride(
        string $key,
        ScopeSignature $signature,
    ): void;
}
