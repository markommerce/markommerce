# Task 013: Wire `SecretCipher` into `ConfigWriter` + `ConfigResolver`

**Status**: pending
**Depends on**: 009, 010, 012
**Retry count**: 0

## Description
Integrate the cipher transparently into the read/write boundaries so `#[Config(secret: true)]` values are encrypted before persistence and decrypted on resolution. Application code (writer callers, resolver consumers) sees only plaintext. The cipher is invoked exactly once on each side: encrypt on write, decrypt on read — both gated by the `ConfigDefinition::$secret` flag.

**Important**: tasks 009 and 010 already declared `SecretCipherInterface` as a constructor dependency on `ConfigWriter` and `ConfigResolver` (using a `NullSecretCipher` stub from task 012). This task adds the BEHAVIOR that uses that dependency on the secret read/write paths. **No constructor changes are needed.** Workers on tasks 009 and 010 stub the cipher into their fakes; this task swaps the stub for behavior and adds the secret-branch tests.

## Context
- `ConfigWriter::setGlobal($key, $value)` / `setOverride(...)` should, when `$definition->secret === true`:
  1. JSON-encode the plaintext value to a string
  2. Pass the string through `SecretCipher::encrypt(...)`
  3. Store the base64 ciphertext as the value in the row
- `ConfigResolver::resolved(...)` should, on a row hit and when `$definition->secret === true`:
  1. The raw stored value is a base64 ciphertext string
  2. Pass it through `SecretCipher::decrypt(...)` → JSON string → `json_decode(...)` → cast via `ValueCaster`
- For non-secret configs, the cipher is bypassed entirely — `NullSecretCipher` is never invoked, so the boot-time lazy validation (task 020) does not fire either
- Add the secret-branch logic to the existing `ConfigWriter::setGlobal/setOverride` and `ConfigResolver::resolved` methods. Do NOT change constructor signatures.
- Add a deterministic `IdentitySecretCipher` test double (plaintext = ciphertext) under `tests/Unit/Cipher/` for the round-trip tests
- Existing tests from tasks 009 and 010 stay unchanged (they don't touch secret configs)

## Requirements (Test Descriptions)
- [ ] `it encrypts the value with SecretCipher when writing a #[Config(secret: true)] global`
- [ ] `it encrypts the value with SecretCipher when writing a #[Config(secret: true)] override`
- [ ] `it decrypts the stored ciphertext when resolving a #[Config(secret: true)] global`
- [ ] `it decrypts the stored ciphertext when resolving a #[Config(secret: true)] override`
- [ ] `it does not invoke the cipher when the property is not marked secret`
- [ ] `it round-trips a secret value through write then read using SodiumSecretCipher with a real 32-byte key`

## Acceptance Criteria
- `ConfigWriter` and `ConfigResolver` already accept `SecretCipherInterface` from tasks 009 and 010 — this task only adds the runtime branching for `secret: true` definitions
- No constructor signatures change in this task
- All previous tests from tasks 009 and 010 still pass (regression-free)
- New tests cover the secret/non-secret branching in both classes
- PHPStan level 8 clean

## Implementation Notes
(Left blank — filled in by programmer)
