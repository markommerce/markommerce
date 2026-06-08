<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Page;

use Markommerce\Criteria\Exceptions\InvalidPageSizeException;
use Markommerce\Criteria\Sort\Sort;

readonly class PageRequest
{
    private function __construct(
        public int $size,
        public Sort $sort,
        public ?string $position,
    ) {}

    /**
     * @throws InvalidPageSizeException
     */
    public static function first(int $size, Sort $sort): self
    {
        self::guardSize($size);

        return new self(size: $size, sort: $sort, position: null);
    }

    /**
     * @throws InvalidPageSizeException
     */
    public static function at(int $size, Sort $sort, string $position): self
    {
        self::guardSize($size);

        return new self(size: $size, sort: $sort, position: $position);
    }

    /**
     * @throws InvalidPageSizeException
     */
    private static function guardSize(int $size): void
    {
        if ($size <= 0) {
            throw InvalidPageSizeException::forSize($size);
        }
    }
}
