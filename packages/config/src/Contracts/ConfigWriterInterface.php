<?php

declare(strict_types=1);

namespace Markommerce\Config\Contracts;

use Markommerce\Config\Exceptions\AxisNotDeclaredException;
use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Exceptions\StaleConfigWriteException;
use Markommerce\Scope\Signature\ScopeSignature;

interface ConfigWriterInterface
{
    /**
     * @throws ConfigNotFoundException
     * @throws StaleConfigWriteException
     */
    public function setGlobal(
        string $key,
        mixed $value,
    ): void;

    /**
     * @throws ConfigNotFoundException
     * @throws StaleConfigWriteException
     */
    public function unsetGlobal(string $key): void;

    /**
     * @throws ConfigNotFoundException
     * @throws AxisNotDeclaredException
     * @throws StaleConfigWriteException
     */
    public function setOverride(
        string $key,
        ScopeSignature $signature,
        mixed $value,
    ): void;

    /**
     * @throws ConfigNotFoundException
     * @throws AxisNotDeclaredException
     * @throws StaleConfigWriteException
     */
    public function unsetOverride(
        string $key,
        ScopeSignature $signature,
    ): void;
}
