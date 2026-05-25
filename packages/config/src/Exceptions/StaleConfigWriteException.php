<?php

declare(strict_types=1);

namespace Markommerce\Config\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class StaleConfigWriteException extends MarkoException
{
    public static function afterRetries(
        string $key,
        int $retryCount,
    ): self
    {
        return new self(
            message: "Failed to write config key '$key' after $retryCount optimistic-lock retries",
            context: "Writing config key '$key' — concurrent writes caused version conflicts that exceeded the maximum retry count of $retryCount",
            suggestion: "Retry the operation, reduce the frequency of concurrent writes to this key, or increase the retry limit if high contention is expected",
        );
    }
}
