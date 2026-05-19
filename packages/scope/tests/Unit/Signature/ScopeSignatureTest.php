<?php

declare(strict_types=1);

use Markommerce\Scope\Exceptions\InvalidSignatureException;
use Markommerce\Scope\Signature\ScopeSignature;

it('constructs a signature from an associative array of axis to value', function (): void {
    $signature = new ScopeSignature(['channel' => 'b2b']);

    expect($signature)->toBeInstanceOf(ScopeSignature::class);
});

it('stores axes alphabetically sorted regardless of input array order', function (): void {
    $signature = new ScopeSignature(['locale' => 'es', 'channel' => 'b2b']);

    expect($signature->axes())->toBe(['channel', 'locale']);
});

it('serializes to the canonical pipe-separated string with axes alphabetically ordered', function (): void {
    $signature = new ScopeSignature(['locale' => 'es', 'channel' => 'b2b']);

    expect($signature->toString())->toBe('channel:b2b|locale:es');
});

it('round-trips fromString through toString to the same canonical string', function (): void {
    $original = 'channel:b2b|locale:es';
    $signature = ScopeSignature::fromString($original);

    expect($signature->toString())->toBe($original);
});

it('round-trips a single-axis signature through fromString and toString', function (): void {
    $original = 'channel:b2b';
    $signature = ScopeSignature::fromString($original);

    expect($signature->toString())->toBe($original);
});

it('round-trips a three-axis signature through fromString and toString', function (): void {
    $original = 'channel:b2b|locale:es|market:eu.es';
    $signature = ScopeSignature::fromString($original);

    expect($signature->toString())->toBe($original);
});

it('produces equal signatures from equivalent inputs regardless of array order', function (): void {
    $a = new ScopeSignature(['locale' => 'es', 'channel' => 'b2b']);
    $b = new ScopeSignature(['channel' => 'b2b', 'locale' => 'es']);

    expect($a->equals($b))->toBeTrue();
});

it('considers two signatures with different axes or values unequal', function (): void {
    $a = new ScopeSignature(['channel' => 'b2b']);
    $b = new ScopeSignature(['channel' => 'b2c']);

    expect($a->equals($b))->toBeFalse();
});

it('returns true from hasAxis when the axis is present', function (): void {
    $signature = new ScopeSignature(['channel' => 'b2b', 'locale' => 'es']);

    expect($signature->hasAxis('locale'))->toBeTrue();
});

it('returns false from hasAxis when the axis is absent', function (): void {
    $signature = new ScopeSignature(['channel' => 'b2b']);

    expect($signature->hasAxis('locale'))->toBeFalse();
});

it('returns the value for a present axis via get', function (): void {
    $signature = new ScopeSignature(['channel' => 'b2b', 'locale' => 'es']);

    expect($signature->get('locale'))->toBe('es');
});

it('returns null from get when the axis is absent', function (): void {
    $signature = new ScopeSignature(['channel' => 'b2b']);

    expect($signature->get('locale'))->toBeNull();
});

it('returns the alphabetically sorted axes list via axes', function (): void {
    $signature = new ScopeSignature(['market' => 'eu.es', 'channel' => 'b2b', 'locale' => 'es']);

    expect($signature->axes())->toBe(['channel', 'locale', 'market']);
});

it('throws InvalidSignatureException when constructed with an empty array', function (): void {
    expect(fn () => new ScopeSignature([]))->toThrow(InvalidSignatureException::class);
});

it('throws InvalidSignatureException when an axis name is empty', function (): void {
    expect(fn () => new ScopeSignature(['' => 'b2b']))->toThrow(InvalidSignatureException::class);
});

it('throws InvalidSignatureException when an axis value is empty', function (): void {
    expect(fn () => new ScopeSignature(['channel' => '']))->toThrow(InvalidSignatureException::class);
});

it('throws InvalidSignatureException when fromString receives a malformed string', function (): void {
    expect(fn () => ScopeSignature::fromString('no-colon-here'))->toThrow(InvalidSignatureException::class);
});

it('throws InvalidSignatureException when fromString receives an empty string', function (): void {
    expect(fn () => ScopeSignature::fromString(''))->toThrow(InvalidSignatureException::class);
});

it('throws InvalidSignatureException when fromString receives a signature with duplicate axes (concrete: "locale:es|locale:de")', function (): void {
    expect(fn () => ScopeSignature::fromString('locale:es|locale:de'))->toThrow(InvalidSignatureException::class);
});

it('throws InvalidSignatureException when fromString receives a part containing more than one colon (concrete: "locale:es:extra")', function (): void {
    expect(fn () => ScopeSignature::fromString('locale:es:extra'))->toThrow(InvalidSignatureException::class);
});

it('precomputes toString once in the constructor (no recomputation on repeated calls)', function (): void {
    $signature = new ScopeSignature(['channel' => 'b2b', 'locale' => 'es']);

    $first = $signature->toString();
    $second = $signature->toString();

    expect($first)->toBe($second)->toBe('channel:b2b|locale:es');
});
