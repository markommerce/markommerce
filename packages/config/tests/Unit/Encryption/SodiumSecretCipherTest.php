<?php

declare(strict_types=1);

use Markommerce\Config\Encryption\SodiumSecretCipher;
use Markommerce\Config\Exceptions\SecretCipherException;

$validKey = str_repeat('a', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);

it('round-trips a plaintext string through encrypt and decrypt (SodiumSecretCipher)', function () use ($validKey): void {
    $cipher = new SodiumSecretCipher($validKey);
    $plaintext = 'my secret value';

    $ciphertext = $cipher->encrypt($plaintext);
    $decrypted = $cipher->decrypt($ciphertext);

    expect($decrypted)->toBe($plaintext);
});

it('produces a different ciphertext for the same plaintext on each call (nonce randomization)', function () use ($validKey): void {
    $cipher = new SodiumSecretCipher($validKey);
    $plaintext = 'my secret value';

    $ciphertext1 = $cipher->encrypt($plaintext);
    $ciphertext2 = $cipher->encrypt($plaintext);

    expect($ciphertext1)->not->toBe($ciphertext2);
});

it('throws a setup exception when constructed if the sodium extension is unavailable', function () use ($validKey): void {
    expect(fn () => new SodiumSecretCipher($validKey, sodiumAvailable: false))
        ->toThrow(SecretCipherException::class);
});

it('throws an exception when constructed with a key of the wrong length', function (): void {
    $shortKey = str_repeat('a', 16);

    expect(fn () => new SodiumSecretCipher($shortKey))
        ->toThrow(SecretCipherException::class);
});

it('returns base64 output from encrypt so the ciphertext is safe to embed in JSON', function () use ($validKey): void {
    $cipher = new SodiumSecretCipher($validKey);
    $ciphertext = $cipher->encrypt('some secret');

    expect(base64_decode($ciphertext, strict: true))->not->toBeFalse()
        ->and($ciphertext)->toMatch('/^[A-Za-z0-9+\/]+=*$/');
});

it('throws an exception on decrypt when the ciphertext is malformed or tampered', function () use ($validKey): void {
    $cipher = new SodiumSecretCipher($validKey);

    // Tampered: modify a byte in a valid ciphertext
    $validCiphertext = $cipher->encrypt('hello');
    $decoded = base64_decode($validCiphertext);
    $decoded[strlen($decoded) - 1] = chr(ord($decoded[strlen($decoded) - 1]) ^ 0xFF);
    $tampered = base64_encode($decoded);

    expect(fn () => $cipher->decrypt('not-base64!!!'))
        ->toThrow(SecretCipherException::class)
        ->and(fn () => $cipher->decrypt($tampered))
        ->toThrow(SecretCipherException::class);
});
