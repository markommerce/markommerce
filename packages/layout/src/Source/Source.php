<?php

declare(strict_types=1);

namespace Markommerce\Layout\Source;

use InvalidArgumentException;

class Source
{
    /** @var array<int, string> */
    private const array ALLOWED_CASTS = ['int', 'string', 'bool'];

    public static function route(
        string $name,
        string $as = 'string',
    ): RouteSource {
        self::assertValidCast($as);

        return new RouteSource($name, $as);
    }

    public static function query(
        string $name,
        mixed $default = null,
        string $as = 'string',
    ): QuerySource {
        self::assertValidCast($as);

        return new QuerySource($name, $default, $as);
    }

    public static function context(
        string $token,
        ?string $path = null,
    ): ContextSource {
        return new ContextSource($token, $path);
    }

    public static function iterated(
        string $token,
        ?string $path = null,
    ): IteratedSource {
        return new IteratedSource($token, $path);
    }

    public static function parentData(
        string $key,
        string $as = 'string',
    ): ParentDataSource {
        return new ParentDataSource($key, $as);
    }

    public static function service(string $class): ServiceSource
    {
        return new ServiceSource($class);
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function assertValidCast(string $as): void
    {
        if (!in_array($as, self::ALLOWED_CASTS, true)) {
            $allowed = implode(', ', self::ALLOWED_CASTS);
            throw new InvalidArgumentException(
                "Invalid cast keyword '$as'. Allowed values are: $allowed.",
            );
        }
    }
}
