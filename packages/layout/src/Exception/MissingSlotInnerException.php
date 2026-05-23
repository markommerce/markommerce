<?php

declare(strict_types=1);

namespace Markommerce\Layout\Exception;

class MissingSlotInnerException extends LayoutException
{
    public static function forDecorator(string $decoratorClass): self
    {
        return new self(
            message: "Decorator '$decoratorClass' template is missing the required `{slot inner}` placeholder.",
            context: "Validating decorator '$decoratorClass' during compile-time WrapWith resolution.",
            suggestion: "Add `{slot inner}` to the template returned by '$decoratorClass::template()' so the renderer knows where to inject the wrapped component's HTML.",
        );
    }
}
