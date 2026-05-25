<?php

declare(strict_types=1);

namespace Markommerce\Config\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class SecretCipherException extends MarkoException
{
    public static function notConfigured(): self
    {
        return new self(
            message: 'Secret cipher key is not configured',
            context: 'Attempting to encrypt or decrypt a secret config value — no cipher key is available',
            suggestion: 'Set the MARKOMMERCE_CONFIG_SECRET_KEY environment variable to a 32-byte base64-encoded key before using secret config values',
        );
    }

    public static function sodiumUnavailable(): self
    {
        return new self(
            message: 'The sodium PHP extension is not available',
            context: 'Attempting to encrypt or decrypt a secret config value — sodium extension is required',
            suggestion: 'Install the sodium PHP extension (ext-sodium) and ensure it is enabled in your PHP configuration',
        );
    }

    public static function invalidKeyLength(
        int $actualLength,
        int $expectedLength,
    ): self {
        return new self(
            message: "Secret cipher key has invalid length: got $actualLength bytes, expected $expectedLength bytes",
            context: 'Initialising secret cipher — the provided key does not meet the required length',
            suggestion: "Generate a valid key of $expectedLength bytes and store it in the MARKOMMERCE_CONFIG_SECRET_KEY environment variable",
        );
    }

    public static function tamperedCiphertext(string $key): self
    {
        return new self(
            message: "Decryption failed for config key '$key' — ciphertext may have been tampered with",
            context: "Decrypting secret config value for key '$key' — authentication tag verification failed",
            suggestion: "Re-encrypt the stored value for '$key' using the current cipher key, or restore the value from a trusted backup",
        );
    }
}
