<?php

declare(strict_types=1);

namespace Markommerce\Config\Encryption;

use Markommerce\Config\Contracts\SecretCipherInterface;
use Markommerce\Config\Exceptions\SecretCipherException;

class NullSecretCipher implements SecretCipherInterface
{
    /**
     * @throws SecretCipherException
     */
    public function encrypt(string $plaintext): string
    {
        throw SecretCipherException::notConfigured();
    }

    /**
     * @throws SecretCipherException
     */
    public function decrypt(string $ciphertext): string
    {
        throw SecretCipherException::notConfigured();
    }
}
