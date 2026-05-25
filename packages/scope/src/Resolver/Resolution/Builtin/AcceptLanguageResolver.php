<?php

declare(strict_types=1);

namespace Markommerce\Scope\Resolver\Resolution\Builtin;

use Markommerce\Scope\Axis\ScopeAxis;
use Markommerce\Scope\Resolver\Resolution\ScopeAxisResolverInterface;
use Markommerce\Scope\Resolver\Resolution\ScopeResolutionContext;

/**
 * Resolves scope axis from the Accept-Language HTTP header.
 *
 * Parses the Accept-Language header (RFC 7231 format with q-values), sorts
 * candidates by preference descending, and returns the first language tag
 * that exists in the axis hierarchy. Falls back to the bare language code
 * (without region) if the full tag is not found. Returns null when the
 * channel is not HTTP, the header is absent/empty, or no candidate matches.
 *
 * Note: Language tags are lowercased before hierarchy lookup because scope
 * paths follow the lowercase convention, while HTTP headers are case-insensitive.
 */
readonly class AcceptLanguageResolver implements ScopeAxisResolverInterface
{
    public function resolve(
        ScopeAxis $scopeAxis,
        ScopeResolutionContext $scopeResolutionContext,
    ): ?string
    {
        if ($scopeResolutionContext->channel !== ScopeResolutionContext::CHANNEL_HTTP) {
            return null;
        }

        $header = $scopeResolutionContext->request->header('Accept-Language');

        if ($header === null || $header === '') {
            return null;
        }

        $candidates = $this->parseHeader($header);

        if ($candidates === []) {
            return null;
        }

        // Sort by q-value descending (stable sort: equal q keeps original order)
        usort($candidates, static fn (array $a, array $b): int => $b['q'] <=> $a['q']);

        // Iterate candidates in q-value descending order.
        // For each candidate: first try an exact hierarchy match, then (if the tag
        // has a region component) fall back to the bare language code.
        // The region-stripped fallback is attempted only after all other regional
        // variants at the same or higher q-value have failed to match exactly —
        // this is naturally enforced by the sorted iteration order.

        foreach ($candidates as $candidate) {
            $tag = $candidate['tag'];

            if ($scopeAxis->hierarchy->exists($tag)) {
                return $tag;
            }

            $dashPos = strpos($tag, '-');
            if ($dashPos !== false) {
                $bareCode = substr($tag, 0, $dashPos);
                if ($scopeAxis->hierarchy->exists($bareCode)) {
                    return $bareCode;
                }
            }
        }

        return null;
    }

    /**
     * Parse the Accept-Language header into an array of ['tag' => string, 'q' => float] entries.
     * Skips malformed tokens and tokens with q-values outside [0, 1].
     *
     * @return list<array{tag: string, q: float}>
     */
    private function parseHeader(string $header): array
    {
        $result = [];

        foreach (explode(',', $header) as $token) {
            $token = trim($token);

            if ($token === '') {
                continue;
            }

            $parts = explode(';', $token);
            $tag = strtolower(trim($parts[0]));

            if ($tag === '' || !preg_match('/^[a-z]{1,8}(-[a-z0-9]{1,8})*$/', $tag)) {
                continue;
            }

            $q = 1.0;

            if (isset($parts[1])) {
                $qPart = trim($parts[1]);

                if (!preg_match('/^q\s*=\s*([0-9]*\.?[0-9]+)$/i', $qPart, $matches)) {
                    continue;
                }

                $q = (float) $matches[1];

                if ($q < 0.0 || $q > 1.0) {
                    continue;
                }
            }

            $result[] = ['tag' => $tag, 'q' => $q];
        }

        return $result;
    }
}
