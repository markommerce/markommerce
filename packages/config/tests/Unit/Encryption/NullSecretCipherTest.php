<?php

declare(strict_types=1);

use Markommerce\Config\Encryption\NullSecretCipher;
use Markommerce\Config\Exceptions\SecretCipherException;

it('NullSecretCipher throws SecretCipherException::notConfigured from encrypt and decrypt', function (): void {
    $cipher = new NullSecretCipher();

    expect(fn () => $cipher->encrypt('anything'))
        ->toThrow(SecretCipherException::class)
        ->and(fn () => $cipher->decrypt('anything'))
        ->toThrow(SecretCipherException::class);
});

it(
    'NullSecretCipher can be constructed without arguments so it can serve as a default container binding',
    function (): void {
        $cipher = new NullSecretCipher();

        expect($cipher)->toBeInstanceOf(NullSecretCipher::class);
    },
);
