<?php

declare(strict_types=1);

namespace Markommerce\Scope\Signature;

use Markommerce\Scope\Exceptions\InvalidSignatureException;

readonly class ScopeSignature
{
    /** @var array<string, string> */
    private array $axes;

    private string $serialized;

    /**
     * @param array<string, string> $axisValues
     *
     * @throws InvalidSignatureException
     */
    public function __construct(array $axisValues)
    {
        if ($axisValues === []) {
            throw InvalidSignatureException::emptyArray();
        }

        foreach ($axisValues as $axis => $value) {
            if ($axis === '') {
                throw InvalidSignatureException::emptyAxis();
            }
            if ($value === '') {
                throw InvalidSignatureException::emptyValue($axis);
            }
        }

        ksort($axisValues);

        $this->axes = $axisValues;
        $this->serialized = implode(
            '|',
            array_map(
                static fn (string $axis, string $value): string => "$axis:$value",
                array_keys($axisValues),
                array_values($axisValues),
            ),
        );
    }

    /**
     * @param array<string, string> $axisValues
     *
     * @throws InvalidSignatureException
     */
    public static function fromArray(array $axisValues): self
    {
        return new self($axisValues);
    }

    /**
     * @throws InvalidSignatureException
     */
    public static function fromString(string $signature): self
    {
        if ($signature === '') {
            throw InvalidSignatureException::emptyString();
        }

        $parts = explode('|', $signature);
        $axisValues = [];

        foreach ($parts as $part) {
            $segments = explode(':', $part);

            if (count($segments) !== 2 || $segments[0] === '' || $segments[1] === '') {
                throw InvalidSignatureException::malformedString($signature);
            }

            $axis = $segments[0];

            if (array_key_exists($axis, $axisValues)) {
                throw InvalidSignatureException::duplicateAxis($axis, $signature);
            }

            $axisValues[$axis] = $segments[1];
        }

        return new self($axisValues);
    }

    public function toString(): string
    {
        return $this->serialized;
    }

    public function equals(self $other): bool
    {
        return $this->serialized === $other->serialized;
    }

    public function hasAxis(string $axis): bool
    {
        return array_key_exists($axis, $this->axes);
    }

    public function get(string $axis): ?string
    {
        return $this->axes[$axis] ?? null;
    }

    /**
     * @return list<string>
     */
    public function axes(): array
    {
        return array_keys($this->axes);
    }
}
