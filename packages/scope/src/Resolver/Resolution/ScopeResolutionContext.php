<?php

declare(strict_types=1);

namespace Markommerce\Scope\Resolver\Resolution;

use Marko\Routing\Http\Request;
use Markommerce\Scope\Registry\ScopeRegistryInterface;

readonly class ScopeResolutionContext
{
    public const string CHANNEL_HTTP = 'http';
    public const string CHANNEL_CLI = 'cli';
    public const string CHANNEL_QUEUE = 'queue';

    /**
     * @param array<string, string> $resolved
     */
    public function __construct(
        public Request $request,
        public ScopeRegistryInterface $registry,
        public array $resolved,
        public string $channel,
    ) {}
}
