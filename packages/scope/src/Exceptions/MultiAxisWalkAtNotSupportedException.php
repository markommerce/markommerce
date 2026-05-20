<?php

declare(strict_types=1);

namespace Markommerce\Scope\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Markommerce\Scope\Signature\ScopeSignature;

/**
 * Exception thrown when walkAt is called with a multi-axis ScopeSignature.
 *
 * Multi-axis walkAt is not supported in this version. Use walk() with a ScopeContext instead.
 */
class MultiAxisWalkAtNotSupportedException extends MarkoException
{
    public static function forSignature(ScopeSignature $signature): self
    {
        $axisCount = count($signature->axes());
        $sigString = $signature->toString();

        return new self(
            message: "walkAt() does not support multi-axis signatures; got $axisCount axes in '$sigString'",
            context: "Calling ScopeWalker::walkAt() with signature '$sigString'",
            suggestion: 'Pass a single-axis ScopeSignature to walkAt(), or use walk() with a ScopeContext for multi-axis resolution',
        );
    }
}
