<?php

declare(strict_types=1);

namespace Markommerce\Layout\Exception;

class InvalidSourceTypeException extends LayoutException
{
    public static function forSource(string $source, string $value, string $targetType): self
    {
        return new self(
            message: "Invalid source type from '$source': got '$value', expected '$targetType'.",
            context: "Resolving source '$source' — the resolved value of type '$value' cannot be used as '$targetType'.",
            suggestion: "Ensure the source '$source' resolves to a value compatible with '$targetType'.",
        );
    }

    public static function forDisallowedHandleProviderSource(
        string $handleKey,
        string $providerClass,
        string $propKey,
        string $sourceType,
    ): self {
        return new self(
            message: "Source type '$sourceType' is not allowed in ProvideHandle::props (prop '$propKey' on '$providerClass' for handle '$handleKey').",
            context: "Compiling ProvideHandle for handle '$handleKey' — prop '$propKey' uses '$sourceType' which is not valid in a handle provider context.",
            suggestion: "Use only RouteSource, QuerySource, ContextSource, ServiceSource, or literal scalars in ProvideHandle::props. ParentDataSource and IteratedSource are not allowed.",
        );
    }
}
