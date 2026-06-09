<?php

declare(strict_types=1);

namespace Markommerce\Catalog\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class NodeNotInTreeException extends MarkoException
{
    public static function forNodeAndTree(
        int $nodeId,
        int $expectedTreeId,
        int $actualTreeId,
    ): self
    {
        return new self(
            message: "Node $nodeId belongs to tree $actualTreeId, not the expected tree $expectedTreeId",
            context: "While validating node $nodeId against tree $expectedTreeId",
            suggestion: 'Ensure the node ID belongs to the correct tree, or load the node from the target tree directly',
        );
    }

    public static function forParentMismatch(
        int $nodeId,
        ?int $expectedParentNodeId,
        ?int $actualParentNodeId,
    ): self
    {
        $expected = $expectedParentNodeId === null ? 'null (root)' : (string) $expectedParentNodeId;
        $actual = $actualParentNodeId === null ? 'null (root)' : (string) $actualParentNodeId;

        return new self(
            message: "Node $nodeId has parent $actual, but parent $expected was expected",
            context: "While validating sibling group for node $nodeId",
            suggestion: 'Ensure all reordered nodes share the same parent, or fetch them via findChildren(parentId, treeId) before calling reorderSiblings',
        );
    }
}
