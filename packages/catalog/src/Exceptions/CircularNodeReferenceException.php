<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class CircularNodeReferenceException extends MarkoException
{
    public static function forNodeAndParent(
        int $nodeId,
        int $proposedParentId,
    ): self {
        return new self(
            message: "Cannot set node $proposedParentId as parent of node $nodeId: this would create a circular reference",
            context: "While reparenting node $nodeId to proposed parent $proposedParentId",
            suggestion: 'Place the node under a different parent that is not a descendant of the node being moved',
        );
    }
}
