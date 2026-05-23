# Task 012: `SecretCipherInterface` + `SodiumSecretCipher`

**Status**: pending
**Depends on**: 002
**Retry count**: 0

## Description
Define the encryption contract for `#[Config(secret: true)]` fields and ship a libsodium-based default implementation. The cipher operates on the JSON-encoded string form of a value (before it's written into the storage row) — so for secrets the raw `value` / `overrides` entries in `config_values` contain an encrypted ciphertext blob, not plaintext.

## Context
- PHP 8.5 ships with the `sodium` extension built-in — no composer dep needed; verify availability via `function_exists('sodium_crypto_secretbox')` at construction time and throw a setup-guidance exception otherwise
- Use XChaCha20-Poly1305 (via `sodium_crypto_secretbox` with a 24-byte XChaCha nonce, OR `sodium_crypto_aead_xchacha20poly1305_ietf_encrypt` — both viable; pick one and stick with it)
- Key is a 32-byte binary string injected via constructor. Module wiring (task 020) sources it from Marko's framework config (env var `MARKOMMERCE_CONFIG_SECRET_KEY`, base64-decoded)
- Output format: base64-encoded `nonce . ciphertext` so it's safe to store in JSON
- Interface methods: `encrypt(string $plaintext): string` and `decrypt(string $ciphertext): string`
- A `NullSecretCipher` IS needed as a default stub binding so `ConfigResolver` and `ConfigWriter` can be constructed before secrets are configured. Its `encrypt`/`decrypt` methods throw `SecretCipherException::notConfigured()` (extend task 002 exception set) with the env-var name to set. It is never invoked for non-secret configs, so apps that don't use secrets never hit it. Task 020's lazy binding swaps `NullSecretCipher` for `SodiumSecretCipher` at runtime if the key is configured.
- Independent of the encryption work: this task does NOT integrate the cipher into writer/resolver yet — task 013 adds the secret-branch behavior (constructor deps are already in place from tasks 009 and 010 per the C4 refactor).

## Requirements (Test Descriptions)
- [ ] `it round-trips a plaintext string through encrypt and decrypt (SodiumSecretCipher)`
- [ ] `it produces a different ciphertext for the same plaintext on each call (nonce randomization)`
- [ ] `it throws a setup exception when constructed if the sodium extension is unavailable`
- [ ] `it throws an exception when constructed with a key of the wrong length`
- [ ] `it throws an exception on decrypt when the ciphertext is malformed or tampered`
- [ ] `it returns base64 output from encrypt so the ciphertext is safe to embed in JSON`
- [ ] `NullSecretCipher throws SecretCipherException::notConfigured from encrypt and decrypt`
- [ ] `NullSecretCipher can be constructed without arguments so it can serve as a default container binding`

## Acceptance Criteria
- `SecretCipherInterface` defines `encrypt(string $plaintext): string` and `decrypt(string $ciphertext): string`
- `SodiumSecretCipher` is the default impl, non-`final`
- Key length validation in constructor
- PHPStan level 8 clean
- `@throws` tags accurate
- Note: the failure-mode tests (`sodium unavailable`, `wrong key length`, `tampered ciphertext`) may need to throw a new dedicated exception type — extend task 002's exception set if so (`SecretCipherException` or similar)

## Implementation Notes
(Left blank — filled in by programmer)
