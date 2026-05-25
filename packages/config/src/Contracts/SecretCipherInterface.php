<?php

declare(strict_types=1);

namespace Markommerce\Config\Contracts;

use Markommerce\Config\Exceptions\SecretCipherException;

interface SecretCipherInterface
{
    /**
     * @throws SecretCipherException
     */
    public function encrypt(string $plaintext): string;

    /**
     * @throws SecretCipherException
     */
    public function decrypt(string $ciphertext): string;
}
