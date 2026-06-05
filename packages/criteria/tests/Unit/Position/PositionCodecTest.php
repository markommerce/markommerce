<?php

declare(strict_types=1);

use Markommerce\Criteria\Exceptions\InvalidPositionTokenException;
use Markommerce\Criteria\Position\KeysetPosition;
use Markommerce\Criteria\Position\OffsetPosition;
use Markommerce\Criteria\Position\PositionCodec;

it('round-trips an offset position carrying a target page number', function (): void {
    $codec = new PositionCodec();
    $original = new OffsetPosition(page: 5);

    $token = $codec->encode($original);
    $decoded = $codec->decode($token);

    assert($decoded instanceof OffsetPosition);
    expect($decoded)->toBeInstanceOf(OffsetPosition::class)
        ->and($decoded->page)->toBe(5);
});

it('round-trips a keyset position carrying anchor values and an id', function (): void {
    $codec = new PositionCodec();
    $original = new KeysetPosition(anchor: ['name' => 'foo', 'price' => 9.99], id: 42);

    $token = $codec->encode($original);
    $decoded = $codec->decode($token);

    assert($decoded instanceof KeysetPosition);
    expect($decoded)->toBeInstanceOf(KeysetPosition::class)
        ->and($decoded->anchor)->toBe(['name' => 'foo', 'price' => 9.99])
        ->and($decoded->id)->toBe(42);
});

it('tags decoded positions with their type', function (): void {
    $codec = new PositionCodec();

    $offsetToken = $codec->encode(new OffsetPosition(page: 1));
    $keysetToken = $codec->encode(new KeysetPosition(anchor: ['id' => 1], id: 1));

    expect($codec->decode($offsetToken)->type())->toBe('offset')
        ->and($codec->decode($keysetToken)->type())->toBe('keyset');
});

it('produces url-safe tokens with no padding or reserved characters', function (): void {
    $codec = new PositionCodec();

    $offsetToken = $codec->encode(new OffsetPosition(page: 999));
    $keysetToken = $codec->encode(new KeysetPosition(anchor: ['k' => 'v'], id: 1));

    foreach ([$offsetToken, $keysetToken] as $token) {
        expect($token)->not->toContain('+')
            ->and($token)->not->toContain('/')
            ->and($token)->not->toContain('=');
    }
});

it('rejects a structurally malformed token with a loud exception', function (): void {
    $codec = new PositionCodec();

    expect(fn () => $codec->decode('not-valid-base64url!!!'))
        ->toThrow(InvalidPositionTokenException::class);
});

it('rejects a token with an unknown format version with a loud exception', function (): void {
    $codec = new PositionCodec();

    // Manually craft a token with an unknown version
    $payload = json_encode(['v' => 999, 't' => 'offset', 'page' => 1]);
    assert(is_string($payload));
    $token = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');

    expect(fn () => $codec->decode($token))
        ->toThrow(InvalidPositionTokenException::class);
});
