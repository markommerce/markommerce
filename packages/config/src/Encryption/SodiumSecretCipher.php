<?php

declare(strict_types=1);

namespace Markommerce\Config\Encryption;

use Markommerce\Config\Contracts\SecretCipherInterface;
use Markommerce\Config\Exceptions\SecretCipherException;

class SodiumSecretCipher implements SecretCipherInterface
{
    /**
     * @throws SecretCipherException
     */
    public function __construct(
        private readonly string $key,
        ?bool $sodiumAvailable = null,
    )
    {
        if (!($sodiumAvailable ?? extension_loaded('sodium'))) {
            throw SecretCipherException::sodiumUnavailable();
        }

        if (strlen($this->key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw SecretCipherException::invalidKeyLength(strlen($this->key), SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        }
    }

    /**
     * @throws SecretCipherException
     */
    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return base64_encode($nonce . $ciphertext);
    }

    /**
     * @throws SecretCipherException
     */
    public function decrypt(string $ciphertext): string
    {
        $decoded = base64_decode($ciphertext, strict: true);

        if ($decoded === false || strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw SecretCipherException::tamperedCiphertext($ciphertext);
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($encrypted, $nonce, $this->key);

        if ($plaintext === false) {
            throw SecretCipherException::tamperedCiphertext($ciphertext);
        }

        return $plaintext;
    }
}
