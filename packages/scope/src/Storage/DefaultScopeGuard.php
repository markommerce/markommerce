<?php

declare(strict_types=1);

namespace Markommerce\Scope\Storage;

use Markommerce\Scope\Exceptions\ScopeStorageException;

class DefaultScopeGuard
{
    /** @var array<string, string> */
    private static array $axisDefaults = [];

    /** @param array<string, string> $axisDefaults */
    public static function configure(array $axisDefaults): void
    {
        self::$axisDefaults = $axisDefaults;
    }

    public static function reset(): void
    {
        self::$axisDefaults = [];
    }

    public static function isConfigured(): bool
    {
        return self::$axisDefaults !== [];
    }

    /** @throws ScopeStorageException */
    public static function assertWritable(string $signature): void
    {
        if (self::$axisDefaults === []) {
            return;
        }

        foreach (explode('|', $signature) as $part) {
            $segments = explode(':', $part);
            if (count($segments) !== 2) {
                continue;
            }
            [$axis, $value] = $segments;
            if (isset(self::$axisDefaults[$axis]) && self::$axisDefaults[$axis] === $value) {
                throw ScopeStorageException::defaultScopeWrite($axis, $value);
            }
        }
    }
}
