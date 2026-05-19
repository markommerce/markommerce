<?php

declare(strict_types=1);

namespace Markommerce\Scope\Resolution;

use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Exceptions\UnknownScopeException;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Scope;
use Markommerce\Scope\Storage\HasScopesInterface;

class ScopeWalker
{
    /**
     * @param list<string> $axes
     * @throws UnknownAxisException|UnknownScopeException
     */
    public function walk(
        HasScopesInterface $overrides,
        string $property,
        array $axes,
        ScopeContext $context,
        ScopeRegistryInterface $registry,
    ): ScopeWalkResult {
        foreach ($axes as $axis) {
            $path = $context->get($axis);

            if ($path === null) {
                continue;
            }

            $result = $this->findFirstMatch($overrides, $property, $axis, $path, $registry);

            if ($result->isFound()) {
                return $result;
            }
        }

        return ScopeWalkResult::notFound();
    }

    /**
     * Walk overrides for a single explicit scope, ignoring any ambient ScopeContext.
     *
     * @param list<string> $axes
     * @throws UnknownAxisException|UnknownScopeException
     */
    public function walkAt(
        HasScopesInterface $overrides,
        string $property,
        array $axes,
        Scope $scope,
        ScopeRegistryInterface $registry,
    ): ScopeWalkResult {
        if (!in_array($scope->axisName, $axes, true)) {
            return ScopeWalkResult::notFound();
        }

        return $this->findFirstMatch($overrides, $property, $scope->axisName, $scope->path, $registry);
    }

    /**
     * @throws UnknownAxisException|UnknownScopeException
     */
    private function findFirstMatch(
        HasScopesInterface $overrides,
        string $property,
        string $axis,
        string $path,
        ScopeRegistryInterface $registry,
    ): ScopeWalkResult {
        $walked = $registry->getHierarchy($axis)->walkUp($path);

        $matchedScope = array_find(
            $walked,
            fn (string $scopePath) => $overrides->hasOverride($axis . ':' . $scopePath, $property),
        );

        return $matchedScope !== null
            ? ScopeWalkResult::found($overrides->override($axis . ':' . $matchedScope, $property))
            : ScopeWalkResult::notFound();
    }
}
