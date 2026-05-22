<?php

declare(strict_types=1);

namespace Markommerce\Layout\Exception;

class ChainedHandleProviderException extends LayoutException
{
    public static function forChain(string $providerClass, string $dynamicHandle): self
    {
        return new self(
            message: "Handle provider '$providerClass' resolved '$dynamicHandle', but '$dynamicHandle' itself declares 'handleProviders', which is not allowed.",
            context: "Registering handle provider '$providerClass' — the resolved dynamic handle '$dynamicHandle' itself declares 'handleProviders', creating a nested provider chain.",
            suggestion: "Remove 'handleProviders' from dynamic handle '$dynamicHandle'. Handle providers may only be declared on top-level handles, not on dynamically resolved ones.",
        );
    }
}
