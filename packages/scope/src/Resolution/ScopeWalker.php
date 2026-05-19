<?php

declare(strict_types=1);

namespace Markommerce\Scope\Resolution;

use Markommerce\Scope\Context\ScopeContext;
use Markommerce\Scope\Exceptions\MultiAxisWalkAtNotSupportedException;
use Markommerce\Scope\Exceptions\UnknownAxisException;
use Markommerce\Scope\Exceptions\UnknownScopeException;
use Markommerce\Scope\Registry\ScopeRegistryInterface;
use Markommerce\Scope\Signature\ScopeSignature;
use Markommerce\Scope\Signature\SignatureCandidateEnumerator;
use Markommerce\Scope\Storage\HasScopesInterface;

class ScopeWalker
{
    public function __construct(
        private SignatureCandidateEnumerator $signatureCandidateEnumerator,
    ) {}

    /**
     * @param list<string> $axes
     * @throws UnknownAxisException|UnknownScopeException
     */
    public function walk(
        HasScopesInterface $overrides,
        string $property,
        array $axes,
        ScopeContext $context,
    ): ScopeWalkResult {
        $candidates = $this->signatureCandidateEnumerator->enumerate($axes, $context);

        foreach ($candidates as $sig) {
            $sigString = $sig->toString();

            if ($overrides->hasOverride($sigString, $property)) {
                return ScopeWalkResult::found($overrides->override($sigString, $property));
            }
        }

        return ScopeWalkResult::notFound();
    }

    /**
     * Walk overrides for a single explicit scope, ignoring any ambient ScopeContext.
     *
     * @param list<string> $axes
     * @throws MultiAxisWalkAtNotSupportedException|UnknownAxisException|UnknownScopeException
     */
    public function walkAt(
        HasScopesInterface $overrides,
        string $property,
        array $axes,
        ScopeSignature $signature,
        ScopeRegistryInterface $registry,
    ): ScopeWalkResult {
        if (count($signature->axes()) !== 1) {
            throw MultiAxisWalkAtNotSupportedException::forSignature($signature);
        }

        $axisName = $signature->axes()[0];

        if (!in_array($axisName, $axes, true)) {
            return ScopeWalkResult::notFound();
        }

        /** @var string $path — non-null: axisName is derived from axes(), so get() will always resolve */
        $path = $signature->get($axisName);

        return $this->findFirstMatch($overrides, $property, $axisName, $path, $registry);
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
