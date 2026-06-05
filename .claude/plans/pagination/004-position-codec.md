# Task 004: Opaque versioned position codec

**Status**: complete
**Depends on**: 003
**Retry count**: 0

## Description
Create a `PositionCodec` that encodes/decodes opaque, versioned, URL-safe position tokens. A token carries a format version and a type tag (`offset` | `keyset`) plus its payload (offset: target page; keyset: anchor sort values + id). Tampered, malformed, or unknown-version tokens fail loudly.

## Context
- Files: `packages/criteria/src/Position/PositionCodec.php` (namespace `Markommerce\Criteria\Position`), plus small typed payloads `OffsetPosition` and `KeysetPosition` (readonly) in the same dir.
- Encoding: base64url of a JSON payload `{ v: <int>, t: <type>, ... }`. Keep it dependency-free.
- Decoding returns a typed object exposing the type tag so strategies can reject foreign types via `IncompatiblePositionException` (the codec itself throws on structural/version errors; type-mismatch is enforced by the strategy in 006/008).
- Define version as an explicitly-typed constant.

## Requirements (Test Descriptions)
- [x] `it round-trips an offset position carrying a target page number`
- [x] `it round-trips a keyset position carrying anchor values and an id`
- [x] `it tags decoded positions with their type`
- [x] `it produces url-safe tokens with no padding or reserved characters`
- [x] `it rejects a structurally malformed token with a loud exception`
- [x] `it rejects a token with an unknown format version with a loud exception`

## Acceptance Criteria
- All requirements have passing tests.
- Token format is versioned and the version constant is explicitly typed.
- No decrease in coverage.

## Implementation Notes

- Created `InvalidPositionTokenException` at `packages/criteria/src/Exceptions/InvalidPositionTokenException.php` with `malformed(string $reason)` and `unsupportedVersion(int $version)` factories.
- Created `OffsetPosition` (carries `int $page`) and `KeysetPosition` (carries `array<string,scalar> $anchor` + `int $id`) as `readonly class` in `packages/criteria/src/Position/`.
- Both value objects expose a `type(): string` method returning `'offset'` or `'keyset'`.
- `PositionCodec` uses `FORMAT_VERSION = 1` (typed `private const int`) and encodes/decodes base64url (no padding, `-`/`_` substitution) of a JSON payload `{v, t, ...}`.
- Throws `InvalidPositionTokenException` on: strict base64 failure, invalid JSON, missing/non-integer version, unknown version, missing type, malformed payload fields.
