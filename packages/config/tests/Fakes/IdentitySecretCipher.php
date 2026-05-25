<?php

declare(strict_types=1);

namespace Markommerce\Config\Tests\Fakes;

use Markommerce\Config\Contracts\SecretCipherInterface;

class IdentitySecretCipher implements SecretCipherInterface
{
    public function encrypt(string $plaintext): string
    {
        return $plaintext;
    }

    public function decrypt(string $ciphertext): string
    {
        return $ciphertext;
    }
}
