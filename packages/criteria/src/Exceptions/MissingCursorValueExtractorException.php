<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class MissingCursorValueExtractorException extends MarkoException
{
    public static function forKeyset(): self
    {
        return new self(
            message: 'KeysetPaginationStrategy requires a CursorValueExtractorInterface to encode next/previous anchors',
            context: 'While encoding a keyset pagination anchor after fetching results',
            suggestion: 'Pass a CursorValueExtractorInterface implementation as the third argument to paginate()',
        );
    }
}
