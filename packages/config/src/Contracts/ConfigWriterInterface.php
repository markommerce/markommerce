<?php

declare(strict_types=1);

namespace Markommerce\Config\Contracts;

use Markommerce\Config\Exceptions\ConfigNotFoundException;
use Markommerce\Config\Exceptions\StaleConfigWriteException;

interface ConfigWriterInterface
{
    /**
     * @throws ConfigNotFoundException|StaleConfigWriteException
     */
    public function setGlobal(
        string $key,
        mixed $value,
    ): void;

    /**
     * @throws ConfigNotFoundException|StaleConfigWriteException
     */
    public function unsetGlobal(string $key): void;
}
